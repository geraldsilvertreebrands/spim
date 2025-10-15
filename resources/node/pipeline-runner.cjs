#!/usr/bin/env node

/**
 * Pipeline Runner - Node.js helper for executing JavaScript calculations
 *
 * Reads JSON from stdin with format:
 * {
 *   "code": "JavaScript code to execute",
 *   "items": [
 *     {
 *       "index": 0,
 *       "entityId": "entity-id",
 *       "inputs": { "attribute": "value", ... }
 *     },
 *     ...
 *   ]
 * }
 *
 * Outputs JSON to stdout with format:
 * {
 *   "results": [
 *     {
 *       "value": ...,
 *       "justification": "...",
 *       "confidence": 0.95,
 *       "meta": {}
 *     },
 *     ...
 *   ]
 * }
 */

const vm = require('vm');
const { performance } = require('perf_hooks');

// Read input from stdin
let inputData = '';
process.stdin.setEncoding('utf8');

process.stdin.on('data', (chunk) => {
  inputData += chunk;
});

process.stdin.on('end', () => {
  try {
    const payload = JSON.parse(inputData);
    const results = processPayload(payload);
    console.log(JSON.stringify({ results }));
    process.exit(0);
  } catch (error) {
    console.log(JSON.stringify({
      error: error.message,
      stack: error.stack,
    }));
    process.exit(1);
  }
});

function processPayload(payload) {
  const { code, items } = payload;

  if (!code || !Array.isArray(items)) {
    throw new Error('Invalid payload: must have code and items array');
  }

  const results = [];

  for (const item of items) {
    const startTime = performance.now();

    try {
      const result = executeCode(code, item.inputs, item.entityId);
      const duration = Math.round(performance.now() - startTime);

      results.push({
        value: result.value,
        justification: result.justification || null,
        confidence: result.confidence !== undefined ? result.confidence : null,
        meta: {
          ...result.meta,
          execution_time_ms: duration,
        },
      });
    } catch (error) {
      results.push({
        error: error.message,
        stack: error.stack,
      });
    }
  }

  return results;
}

function executeCode(code, inputs, entityId) {
  // Create sandbox context
  const sandbox = {
    // n8n-style variables
    $json: inputs,
    $input: inputs,
    $entityId: entityId,

    // Safe built-ins
    console: {
      log: (...args) => {
        // Suppress console output for now
      },
    },
    JSON,
    Math,
    Date,
    String,
    Number,
    Boolean,
    Array,
    Object,

    // Helper functions
    parseFloat,
    parseInt,
    isNaN,
    isFinite,
  };

  // Wrap code in function to get return value
  const wrappedCode = `
    (function() {
      ${code}
    })()
  `;

  // Create script with timeout
  const script = new vm.Script(wrappedCode, {
    filename: 'pipeline-calculation.js',
    timeout: 5000, // 5 second timeout per item
  });

  // Execute in sandbox
  const context = vm.createContext(sandbox);
  const result = script.runInContext(context);

  // Validate result
  if (result === null || result === undefined) {
    throw new Error('Code must return a value');
  }

  // If result is not an object, wrap it
  if (typeof result !== 'object' || result === null) {
    return {
      value: result,
      confidence: 1.0,
    };
  }

  // Ensure result has required shape
  if (!result.hasOwnProperty('value')) {
    throw new Error('Result must include a "value" property');
  }

  return result;
}


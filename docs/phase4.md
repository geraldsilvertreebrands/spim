Phase 4 — Approval workflow
---------------------------

Goals
- Provide clear review of pending changes and easy approvals.
- Track differences between current and approved values without extra state machine.

Deliverables
- Review queue screen:
  - List entities with any `versioned` attributes where `value_current != value_approved` (and attribute.review_required != 'no' or confidence < 0.8)
  - Show changed attributes, diffs, justifications, confidence
  - Bulk approve selection
- Approve action updates `value_approved` and triggers sync if needed

Tasks
1) Review query & screen
  - SQL: join `eav_versioned` with `attributes` and `entities`
  - Filter: attributes with review_required = 'always' OR ('low_confidence' AND confidence < 0.8)
  - Group per entity; list changed fields

2) Diff rendering
  - For text/html: simple diff (line/word-level) component
  - For json: pretty-print + json diff

3) Actions
  - Approve single/bulk → set `value_approved = value_current`
  - Reject (optional) → leave as-is; add note (future: history table)

4) Notifications (optional)
  - Email/Slack when items enter the queue

5) Tests
  - Approval filtering logic
  - Approve action updates values correctly

Acceptance criteria
- Review page lists all approvals needed with accurate confidence gating.
- Approve applies the correct values and removes from the queue.
- Bulk approval works.

Open questions
- Should we capture reviewer notes? Suggested: minimal notes field in a new `approval_notes` table keyed by (entity_id, attribute_id, timestamp). 
- Should low-confidence threshold be configurable per attribute? Suggested: attribute-level override field, default 0.8.
Testing plan
- Fixtures: create entities with versioned attributes where current != approved, including low_confidence edge cases.
- Feature tests:
  - Review query lists correct entities/attributes.
  - Diff components render expected changes.
  - Single and bulk approval update `value_approved` and remove from queue.
  - Confidence gating: items with confidence >= 0.8 auto-approve when review_required=low_confidence.

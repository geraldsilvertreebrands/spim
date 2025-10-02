Add concept of attribute sections to this project, that is, group attributes in sections, that affects how they are displayed within the attribute value editing forms. Sections are grouped together.

So:
- change UI menu, to have a section called "Settings", and move "Entity Types" and "Attributes" there
- add DB table for attribute_sections, with fields including product_type, name, sort_order
- add UI section in Settings to allow editing attribute sections
- add fields to attribute editing: attribute_section, and sort_order
- change the entity editing form to group attributes by section.


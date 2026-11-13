-- Client spec: Standard tables are "good for 2 to 4 persons".
UPDATE table_types SET capacity_min = 2 WHERE type_name = 'Standard';

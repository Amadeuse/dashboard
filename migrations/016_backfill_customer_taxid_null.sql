-- '0' has always meant "no tax id" in imported data (see app/Models/Customer.php),
-- but only new submissions normalize it to NULL — 269 legacy rows still have the
-- literal string '0'. migrations/017 adds a real UNIQUE index on this column;
-- that would fail immediately on these 269 duplicates if run first.
UPDATE `customers` SET `customer_taxid` = NULL WHERE `customer_taxid` = '0';

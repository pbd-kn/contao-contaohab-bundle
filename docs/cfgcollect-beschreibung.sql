-- Einmalig auf PC und Raspberry ausfuehren, bevor der angepasste Code verwendet wird.
-- CHANGE benennt die bestehende Spalte um und erhaelt deren Werte.
ALTER TABLE tl_coh_cfgcollect CHANGE COLUMN cfgType beschreibung VARCHAR(255) NOT NULL DEFAULT '';

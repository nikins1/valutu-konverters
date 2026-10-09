-- Sākotnējie dati: valūtu saraksts (pilnu sarakstu vēlāk papildina scripts/sync_rates.php)
INSERT IGNORE INTO currencies (code, name) VALUES
('EUR', 'Euro'), ('USD', 'US Dollar'), ('GBP', 'Pound Sterling'), ('CHF', 'Swiss Franc'),
('JPY', 'Japanese Yen'), ('SEK', 'Swedish Krona'), ('NOK', 'Norwegian Krone'), ('DKK', 'Danish Krone'),
('PLN', 'Polish Zloty'), ('CZK', 'Czech Koruna'), ('HUF', 'Hungarian Forint'), ('RON', 'Romanian Leu'),
('BGN', 'Bulgarian Lev'), ('TRY', 'Turkish Lira'), ('CAD', 'Canadian Dollar'), ('AUD', 'Australian Dollar'),
('CNY', 'Chinese Renminbi Yuan'), ('BRL', 'Brazilian Real');

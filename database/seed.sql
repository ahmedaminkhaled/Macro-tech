USE store_app_db;

INSERT INTO categories (name, description) VALUES
('Ordinateurs', 'PC portables et PC de bureau'),
('Smartphones', 'Téléphones intelligents'),
('Tablettes', 'Tablettes tactiles'),
('Écrans PC', 'Moniteurs et écrans externes'),
('Écouteurs', 'Écouteurs filaires et Bluetooth'),
('Imprimantes', 'Imprimantes laser et jet d’encre'),
('Scanners', 'Scanners bureautiques'),
('Cartouches d’encre', 'Consommables imprimantes'),
('Disques durs', 'HDD et SSD'),
('Chargeurs', 'Chargeurs secteur et USB-C'),
('Montres connectées', 'Wearables et smartwatches');

INSERT INTO products (reference, designation, description, brand, price, quantity, photo_path, category_id) VALUES
('REF-LAP-001', 'Laptop Pro 14', 'Laptop 14 pouces, 16Go RAM, 512Go SSD', 'Dell', 3299.00, 10, 'uploads/products/default_laptop.jpg', 1),
('REF-PHN-001', 'Galaxy S Series', 'Smartphone Android 128Go', 'Samsung', 2199.00, 15, 'uploads/products/default_phone.jpg', 2),
('REF-TAB-001', 'iPad 10', 'Tablette 10.9 pouces Wi-Fi', 'Apple', 1999.00, 8, 'uploads/products/default_tablet.jpg', 3),
('REF-MON-001', 'Monitor 24 FHD', 'Écran 24 pouces Full HD', 'LG', 549.00, 20, 'uploads/products/default_monitor.jpg', 4),
('REF-HDD-001', 'SSD 1TB NVMe', 'Disque SSD NVMe 1To', 'Kingston', 399.00, 25, 'uploads/products/default_ssd.jpg', 9);

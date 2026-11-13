-- Bella Vista Dining: seed data (run after schema.sql)
--
-- Reference data required by the business rules, plus a sample menu.
-- Menu names, descriptions and prices are placeholders for the school project.
-- Admin users are NOT seeded here: create them with scripts/seed-admin.php so
-- passwords are hashed with password_hash() and never stored in the repo.

SET NAMES utf8mb4;

-- Capacities follow the client spec: Standard 2-4, Family 5-8, VIP up to 15 included (max 20).
-- base_capacity = pax covered by the reservation fee; extra_charge applies per pax above it.
INSERT INTO table_types (table_type_id, type_name, capacity_min, capacity_max, base_capacity, reservation_fee, minimum_order, extra_charge) VALUES
 (1, 'Standard', 2, 4, 4, 0.00,    1000.00, 0.00),
 (2, 'Family',   5, 8, 8, 500.00,  0.00,    0.00),
 (3, 'VIP',      9, 20, 15, 2500.00, 0.00,  300.00);

INSERT INTO dining_tables (table_id, table_type_id) VALUES
 ('S1',1),('S2',1),('S3',1),('S4',1),
 ('F1',2),('F2',2),('F3',2),
 ('VIP1',3),('VIP2',3);

INSERT INTO menu_categories (category_id, category_name, image_path, sort_order) VALUES
 (1,'Main Courses','/public/assets/images/menu/placeholder.svg',1),
 (2,'Appetizers & Soups','/public/assets/images/menu/placeholder.svg',2),
 (3,'Desserts & Drinks','/public/assets/images/menu/placeholder.svg',3);

-- item_id follows insertion order (1-50); keep the order stable so IDs do not shift.
INSERT INTO menu_items (category_id, item_name, description, price, image_path) VALUES
 (1,'Tapsilog','Beef tapa with garlic rice, fried egg, and atchara',320.00,'/public/assets/images/menu/items/tapsilog.jpg'),
 (1,'Longsilog','Filipino sausage with garlic rice, fried egg, and tomato relish',280.00,'/public/assets/images/menu/items/longsilog.jpg'),
 (1,'Bangsilog','Crispy marinated milkfish with garlic rice and egg',300.00,'/public/assets/images/menu/items/bangsilog.jpg'),
 (1,'Chicksilog','Marinated chicken with garlic rice, fried egg, and atchara',300.00,'/public/assets/images/menu/items/chicksilog.jpg'),
 (1,'Tocilog','Sweet cured pork with garlic rice and fried egg',290.00,'/public/assets/images/menu/items/tocilog.jpg'),
 (1,'Daingsilog','Marinated dried milkfish with garlic rice and egg',310.00,'/public/assets/images/menu/items/daingsilog.jpg'),
 (1,'Pancit Canton Special','Stir-fried noodles with chicken, vegetables, and shrimp',380.00,'/public/assets/images/menu/items/pancit-canton-special.jpg'),
 (1,'Laing Rice Bowl','Creamy taro leaves with pork served over steamed rice',340.00,'/public/assets/images/menu/items/laing-rice-bowl.jpg'),
 (1,'Bangus Belly Plate','Grilled bangus belly with tomato-onion relish and rice',420.00,'/public/assets/images/menu/items/bangus-belly-plate.jpg'),
 (1,'Seafood Kare-Kare','Mixed seafood and vegetables in peanut sauce with bagoong',520.00,'/public/assets/images/menu/items/seafood-kare-kare.jpg'),
 (2,'Lumpiang Shanghai','Crisp pork spring rolls with sweet chili sauce',220.00,'/public/assets/images/menu/items/lumpiang-shanghai.jpg'),
 (2,'Tokwa’t Baboy','Crispy tofu and pork with soy-vinegar dressing',240.00,'/public/assets/images/menu/items/tokwat-baboy.jpg'),
 (2,'Sinigang na Hipon','Shrimp in tamarind broth with seasonal vegetables',360.00,'/public/assets/images/menu/items/sinigang-na-hipon.jpg'),
 (2,'Sinigang na Baboy','Pork, vegetables, and gabi in a sour tamarind broth',340.00,'/public/assets/images/menu/items/sinigang-na-baboy.jpg'),
 (2,'Kinilaw na Tanigue','Fresh fish cured in vinegar, calamansi, ginger, and chili',420.00,'/public/assets/images/menu/items/kinilaw-na-tanigue.jpg'),
 (2,'Ukoy na Hipon','Crispy shrimp and vegetable fritters with vinegar dip',250.00,'/public/assets/images/menu/items/ukoy-na-hipon.jpg'),
 (2,'Kwek-Kwek Basket','Quail eggs in orange batter with spiced vinegar',180.00,'/public/assets/images/menu/items/kwek-kwek-basket.jpg'),
 (2,'Corn and Cheese Fritters','Sweet corn fritters with a creamy garlic dip',190.00,'/public/assets/images/menu/items/corn-and-cheese-fritters.jpg'),
 (2,'Chicken Empanada','Savory pastry filled with chicken, potato, and vegetables',210.00,'/public/assets/images/menu/items/chicken-empanada.jpg'),
 (2,'Bulalo Soup','Slow-cooked beef shank broth with corn and vegetables',450.00,'/public/assets/images/menu/items/bulalo-soup.jpg'),
 (1,'Beef Kare-Kare','Beef and vegetables in peanut sauce with bagoong',580.00,'/public/assets/images/menu/items/beef-kare-kare.jpg'),
 (1,'Chicken Adobo','Chicken braised in soy, vinegar, garlic, and bay leaves',420.00,'/public/assets/images/menu/items/chicken-adobo.jpg'),
 (1,'Crispy Pata','Deep-fried pork knuckle with soy-vinegar dip',650.00,'/public/assets/images/menu/items/crispy-pata.jpg'),
 (1,'Pork Sinigang','Tender pork and vegetables in a tamarind broth',390.00,'/public/assets/images/menu/items/pork-sinigang.jpg'),
 (1,'Chicken Inasal','Char-grilled marinated chicken with calamansi and annatto',420.00,'/public/assets/images/menu/items/chicken-inasal.jpg'),
 (1,'Lechon Kawali','Crispy pork belly with liver sauce and pickled papaya',480.00,'/public/assets/images/menu/items/lechon-kawali.jpg'),
 (1,'Bicol Express','Pork simmered in coconut milk, shrimp paste, and chili',430.00,'/public/assets/images/menu/items/bicol-express.jpg'),
 (1,'Caldereta de Res','Beef stew with tomato sauce, potatoes, and bell peppers',520.00,'/public/assets/images/menu/items/caldereta-de-res.jpg'),
 (1,'Ginataang Seafood','Seafood and vegetables simmered in coconut milk',500.00,'/public/assets/images/menu/items/ginataang-seafood.jpg'),
 (1,'Pinaputok na Tilapia','Whole tilapia baked with tomato, onion, and ginger',460.00,'/public/assets/images/menu/items/pinaputok-na-tilapia.jpg'),
 (1,'Garlic Fried Rice','Fragrant sinangag with toasted garlic and scallions',100.00,'/public/assets/images/menu/items/garlic-fried-rice.jpg'),
 (1,'Steamed Jasmine Rice','Simple steamed rice for sharing',70.00,'/public/assets/images/menu/items/steamed-jasmine-rice.jpg'),
 (2,'Atchara','Sweet and tangy pickled papaya',90.00,'/public/assets/images/menu/items/atchara.jpg'),
 (2,'Ensaladang Talong','Grilled eggplant salad with tomato, onion, and salted egg',180.00,'/public/assets/images/menu/items/ensaladang-talong.jpg'),
 (2,'Ginisang Monggo','Mung beans sautéed with garlic, tomato, and greens',180.00,'/public/assets/images/menu/items/ginisang-monggo.jpg'),
 (2,'Chopsuey Filipino','Mixed vegetables with chicken and quail eggs',280.00,'/public/assets/images/menu/items/chopsuey-filipino.jpg'),
 (2,'Pinoy Corn on the Cob','Sweet corn brushed with butter and toasted garlic',140.00,'/public/assets/images/menu/items/pinoy-corn-on-the-cob.jpg'),
 (2,'Salted Egg Tomato Salad','Fresh tomatoes, salted egg, onion, and vinegar dressing',160.00,'/public/assets/images/menu/items/salted-egg-tomato-salad.jpg'),
 (1,'Coconut Rice','Steamed rice gently scented with coconut milk',120.00,'/public/assets/images/menu/items/coconut-rice.jpg'),
 (2,'Crispy Kamote Fries','Sweet potato fries with spiced vinegar dip',180.00,'/public/assets/images/menu/items/crispy-kamote-fries.jpg'),
 (3,'Halo-Halo','Shaved ice with sweet beans, fruit, leche flan, and ube',240.00,'/public/assets/images/menu/items/halo-halo.jpg'),
 (3,'Leche Flan','Silky Filipino caramel custard',180.00,'/public/assets/images/menu/items/leche-flan.jpg'),
 (3,'Calamansi Juice','Refreshing citrus cooler made with calamansi',120.00,'/public/assets/images/menu/items/calamansi-juice.jpg'),
 (3,'Ube Halaya','Creamy purple yam dessert topped with toasted coconut',190.00,'/public/assets/images/menu/items/ube-halaya.jpg'),
 (3,'Turon with Langka','Crispy caramelized banana rolls with jackfruit',180.00,'/public/assets/images/menu/items/turon-with-langka.jpg'),
 (3,'Bibingka','Rice cake with salted egg, cheese, and coconut',200.00,'/public/assets/images/menu/items/bibingka.jpg'),
 (3,'Puto Cheese','Steamed rice cakes topped with melted cheese',150.00,'/public/assets/images/menu/items/puto-cheese.jpg'),
 (3,'Mango Sago','Chilled mango dessert with tapioca pearls and cream',220.00,'/public/assets/images/menu/items/mango-sago.jpg'),
 (3,'Sago’t Gulaman','Brown sugar drink with jelly and tapioca pearls',130.00,'/public/assets/images/menu/items/sago-t-gulaman.jpg'),
 (3,'Tablea Hot Chocolate','Rich Filipino cacao drink served warm',160.00,'/public/assets/images/menu/items/tablea-hot-chocolate.jpg');

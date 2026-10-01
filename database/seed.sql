-- ============================================================================
--  iPharmaLink  ::  Demo / Seed Data
--  ⚠ FOR LOCAL DEVELOPMENT ONLY
--  All accounts use password:  Password123!
--  All Naira amounts are illustrative.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `rate_limits`;      TRUNCATE TABLE `login_attempts`;   TRUNCATE TABLE `audit_logs`;
TRUNCATE TABLE `contact_messages`; TRUNCATE TABLE `faqs`;              TRUNCATE TABLE `banners`;
TRUNCATE TABLE `pages`;            TRUNCATE TABLE `payouts`;           TRUNCATE TABLE `wallet_transactions`;
TRUNCATE TABLE `pharmacy_wallets`; TRUNCATE TABLE `commissions`;        TRUNCATE TABLE `coupon_usages`;
TRUNCATE TABLE `coupons`;          TRUNCATE TABLE `notifications`;     TRUNCATE TABLE `wishlist_items`;
TRUNCATE TABLE `wishlists`;        TRUNCATE TABLE `review_images`;      TRUNCATE TABLE `reviews`;
TRUNCATE TABLE `prescriptions`;    TRUNCATE TABLE `delivery_status_history`; TRUNCATE TABLE `deliveries`;
TRUNCATE TABLE `delivery_personnel`; TRUNCATE TABLE `refunds`;          TRUNCATE TABLE `payment_transactions`;
TRUNCATE TABLE `payments`;         TRUNCATE TABLE `payment_gateways`;  TRUNCATE TABLE `order_status_history`;
TRUNCATE TABLE `pharmacy_orders`;  TRUNCATE TABLE `order_items`;        TRUNCATE TABLE `orders`;
TRUNCATE TABLE `cart_items`;       TRUNCATE TABLE `carts`;              TRUNCATE TABLE `inventory_movements`;
TRUNCATE TABLE `product_batches`;  TRUNCATE TABLE `product_images`;     TRUNCATE TABLE `products`;
TRUNCATE TABLE `brands`;           TRUNCATE TABLE `categories`;         TRUNCATE TABLE `pharmacy_settings`;
TRUNCATE TABLE `supplier_documents`; TRUNCATE TABLE `suppliers`;
TRUNCATE TABLE `pharmacy_documents`; TRUNCATE TABLE `pharmacy_staff`;   TRUNCATE TABLE `pharmacies`;
TRUNCATE TABLE `user_addresses`;   TRUNCATE TABLE `user_roles`;         TRUNCATE TABLE `users`;
TRUNCATE TABLE `role_permissions`; TRUNCATE TABLE `permissions`;       TRUNCATE TABLE `roles`;
TRUNCATE TABLE `platform_settings`;

-- ---------------------------------------------------------------------------
--  ROLES
-- ---------------------------------------------------------------------------
INSERT INTO `roles` (`name`,`label`,`description`,`is_system`) VALUES
('super_admin','Super Administrator','Full platform control',1),
('pharmacy_owner','Pharmacy Owner','Owns and operates a pharmacy storefront',1),
('wholesale_supplier','Wholesale Supplier','Supplies pharmaceutical products in bulk to pharmacies',1),
('pharmacy_staff','Pharmacy Staff','Employee of a pharmacy',1),
('delivery_personnel','Delivery Personnel','Handles last-mile delivery',1),
('customer','Customer','Browses and purchases medicines',1);

-- ---------------------------------------------------------------------------
--  PERMISSIONS
-- ---------------------------------------------------------------------------
INSERT INTO `permissions` (`name`,`group_name`,`description`) VALUES
('admin.dashboard.view','admin','View admin dashboard'),
('admin.pharmacy.manage','admin','Approve/suspend/edit pharmacies'),
('admin.product.manage','admin','Moderate all platform products'),
('admin.category.manage','admin','Manage categories and brands'),
('admin.user.manage','admin','Manage customers, staff, riders'),
('admin.order.view','admin','View all orders'),
('admin.finance.manage','admin','Commissions, payouts, refunds'),
('admin.cms.manage','admin','Banners, pages, FAQs'),
('admin.settings.manage','admin','Platform settings'),
('admin.audit.view','admin','View audit logs'),
('pharmacy.dashboard.view','pharmacy','View pharmacy dashboard'),
('pharmacy.profile.manage','pharmacy','Edit own pharmacy profile'),
('pharmacy.settings.manage','pharmacy','Edit own pharmacy settings'),
('pharmacy.product.manage','pharmacy','Create/edit own products'),
('pharmacy.inventory.manage','pharmacy','Adjust own stock'),
('pharmacy.order.manage','pharmacy','Process own sub-orders'),
('pharmacy.staff.manage','pharmacy','Manage pharmacy staff'),
('pharmacy.report.view','pharmacy','View own reports'),
('pharmacy.wallet.view','pharmacy','View own wallet/payouts'),
('supplier.dashboard.view','supplier','View own supplier workspace'),
('customer.order.place','customer','Place orders'),
('customer.profile.manage','customer','Manage own profile'),
('delivery.dashboard.view','delivery','View assigned deliveries'),
('delivery.status.update','delivery','Update delivery status');

INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON p.group_name = r.name
WHERE r.name IN ('super_admin','pharmacy_owner','pharmacy_staff','delivery_personnel','customer');

-- Extra: staff get order + inventory handling
INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT (SELECT id FROM roles WHERE name='pharmacy_staff'), id FROM permissions
WHERE name IN ('pharmacy.dashboard.view','pharmacy.order.manage','pharmacy.inventory.manage','pharmacy.report.view');

INSERT INTO `role_permissions` (`role_id`,`permission_id`)
SELECT (SELECT id FROM roles WHERE name='wholesale_supplier'), id FROM permissions
WHERE name = 'supplier.dashboard.view';

-- ---------------------------------------------------------------------------
--  USERS
-- ---------------------------------------------------------------------------
INSERT INTO `users` (`role_id`,`full_name`,`email`,`phone`,`password_hash`,`email_verified_at`,`status`,`created_at`) VALUES
((SELECT id FROM roles WHERE name='super_admin'),'Adaora Nwachukwu','admin@ipharmalink.ng','+2348030000001','$2y$12$jT/mYPsw.OgYkCUVIVTgoOS2sUtEzxOn/hPMs/bfCEMzMWTlNh6/q','2026-01-05 09:00:00','active','2026-01-05 09:00:00'),
((SELECT id FROM roles WHERE name='pharmacy_owner'),'Dr. Chidi Okonkwo','owner@healthplus.ng','+2348031110001','$2y$12$jT/mYPsw.OgYkCUVIVTgoOS2sUtEzxOn/hPMs/bfCEMzMWTlNh6/q','2026-01-06 10:15:00','active','2026-01-06 10:15:00'),
((SELECT id FROM roles WHERE name='pharmacy_owner'),'Amina Bello','owner@medicare.ng','+2348031110002','$2y$12$jT/mYPsw.OgYkCUVIVTgoOS2sUtEzxOn/hPMs/bfCEMzMWTlNh6/q','2026-01-07 11:30:00','active','2026-01-07 11:30:00'),
((SELECT id FROM roles WHERE name='pharmacy_staff'),'Ngozi Eze','staff@healthplus.ng','+2348032220001','$2y$12$jT/mYPsw.OgYkCUVIVTgoOS2sUtEzxOn/hPMs/bfCEMzMWTlNh6/q','2026-01-08 08:20:00','active','2026-01-08 08:20:00'),
((SELECT id FROM roles WHERE name='customer'),'Tunde Adeyemi','customer@demo.ng','+2348044440001','$2y$12$jT/mYPsw.OgYkCUVIVTgoOS2sUtEzxOn/hPMs/bfCEMzMWTlNh6/q','2026-01-09 14:00:00','active','2026-01-09 14:00:00'),
((SELECT id FROM roles WHERE name='customer'),'Blessing Adeyinka','blessing@demo.ng','+2348044440002','$2y$12$jT/mYPsw.OgYkCUVIVTgoOS2sUtEzxOn/hPMs/bfCEMzMWTlNh6/q','2026-01-10 16:45:00','active','2026-01-10 16:45:00'),
((SELECT id FROM roles WHERE name='delivery_personnel'),'Yusuf Musa','rider@demo.ng','+2348055550001','$2y$12$jT/mYPsw.OgYkCUVIVTgoOS2sUtEzxOn/hPMs/bfCEMzMWTlNh6/q','2026-01-11 07:00:00','active','2026-01-11 07:00:00'),
((SELECT id FROM roles WHERE name='pharmacy_owner'),'Fatima Sani','owner@citycare.ng','+2348031110003','$2y$12$jT/mYPsw.OgYkCUVIVTgoOS2sUtEzxOn/hPMs/bfCEMzMWTlNh6/q','2026-02-02 09:30:00','pending','2026-02-02 09:30:00');

INSERT INTO `user_roles` (`user_id`,`role_id`)
SELECT u.id, u.role_id FROM `users` u;

-- ---------------------------------------------------------------------------
--  CUSTOMER ADDRESSES
-- ---------------------------------------------------------------------------
INSERT INTO `user_addresses` (`user_id`,`label`,`recipient_name`,`phone`,`state`,`city`,`address_line`,`landmark`,`latitude`,`longitude`,`is_default`) VALUES
((SELECT id FROM users WHERE email='customer@demo.ng'),'Home','Tunde Adeyemi','+2348044440001','Lagos','Ikeja','14 Obafemi Awolowo Way, Ikeja','Opposite Ikeja City Mall',6.6018,3.3515,1),
((SELECT id FROM users WHERE email='customer@demo.ng'),'Office','Tunde Adeyemi','+2348044440001','Lagos','Victoria Island','12 Adeola Odeku Street, VI','Near Fidelity Bank',6.4280,3.4219,0),
((SELECT id FROM users WHERE email='blessing@demo.ng'),'Home','Blessing Adeyinka','+2348044440002','Lagos','Surulere','3 Bode Thomas Street, Surulere','Behind Trade Fair',6.5021,3.3542,1);

-- ---------------------------------------------------------------------------
--  PHARMACIES
-- ---------------------------------------------------------------------------
INSERT INTO `pharmacies`
(`owner_id`,`name`,`slug`,`legal_name`,`registration_number`,`regulatory_body`,`pharmacist_name`,`phone`,`email`,`website`,`description`,`state`,`city`,`address`,`latitude`,`longitude`,`open_time`,`close_time`,`delivery_available`,`pickup_available`,`delivery_radius_km`,`delivery_fee`,`free_delivery_threshold`,`estimated_delivery_minutes`,`min_order_value`,`preparation_minutes`,`accept_orders_automatically`,`bank_name`,`bank_account_name`,`bank_account_number`,`rating_avg`,`rating_count`,`is_featured`,`status`,`created_at`) VALUES
((SELECT id FROM users WHERE email='owner@healthplus.ng'),'HealthPlus Pharmacy','healthplus-pharmacy','HealthPlus Pharmacies Nigeria Ltd','PCN/2024/1187','Pharmacy Council of Nigeria (PCN)','Dr. Chidi Okonkwo','+2348031110001','hello@healthplus.ng','https://healthplus.ng','Community-first pharmacy in Ikeja offering genuine, NAFDAC-approved medicines, wellness products and 24-hour delivery across Lagos mainland.','Lagos','Ikeja','14 Obafemi Awolowo Way, Ikeja',6.6018,3.3515,'07:00:00','22:00:00',1,1,12.00,1500.00,25000.00,45,5000.00,20,1,'Guaranty Trust Bank','HealthPlus Pharmacies Ltd','0123456789',4.60,128,1,'approved','2026-01-06 10:20:00'),

((SELECT id FROM users WHERE email='owner@medicare.ng'),'MediCare Pharmacy Lekki','medicare-pharmacy-lekki','MediCare Lekki Ventures','PCN/2024/2291','Pharmacy Council of Nigeria (PCN)','Amina Bello','+2348031110002','care@medicare.ng','https://medicarelekki.ng','Specialist pharmacy for chronic-condition care, diabetes management and maternal health products with pharmacist consultations.','Lagos','Lekki','22 Fola Osibo Road, Lekki Phase 1',6.4474,3.4723,'08:00:00','21:00:00',1,1,18.00,2000.00,40000.00,60,8000.00,30,0,'Zenith Bank','MediCare Lekki Ventures','2034567891',4.40,86,1,'approved','2026-01-07 11:35:00'),

((SELECT id FROM users WHERE email='owner@citycare.ng'),'CityCare Pharmacy Surulere','citycare-pharmacy-surulere','CityCare Pharmaceuticals','PCN/2025/0412','Pharmacy Council of Nigeria (PCN)','Fatima Sani','+2348031110003','info@citycare.ng',NULL,'New neighbourhood pharmacy pending verification review by the platform compliance team.','Lagos','Surulere','9 Adeleke Adeniji Street, Surulere',6.5012,3.3610,'08:30:00','20:00:00',0,1,5.00,0.00,0.00,90,0.00,25,0,'First Bank','CityCare Pharmaceuticals','3024567892',0.00,0,0,'pending','2026-02-02 09:35:00');

INSERT INTO `pharmacy_staff` (`pharmacy_id`,`user_id`,`job_title`,`can_manage_orders`,`can_manage_inventory`,`can_manage_products`,`can_view_reports`) VALUES
((SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),(SELECT id FROM users WHERE email='staff@healthplus.ng'),'Dispensary Officer',1,1,0,1);

INSERT INTO `pharmacy_wallets` (`pharmacy_id`,`balance`,`pending_balance`,`total_earned`,`total_paid`)
SELECT id,0,0,0,0 FROM `pharmacies`;

INSERT INTO `delivery_personnel` (`user_id`,`pharmacy_id`,`vehicle_type`,`plate_number`,`is_available`) VALUES
((SELECT id FROM users WHERE email='rider@demo.ng'),NULL,'motorcycle','LAG-441-QR',1);

-- ---------------------------------------------------------------------------
--  CATEGORIES (admin-managed, nothing hard-coded in the app)
-- ---------------------------------------------------------------------------
INSERT INTO `categories` (`name`,`slug`,`description`,`icon`,`sort_order`) VALUES
('Prescription Medicines','prescription-medicines','Medicines that require a valid prescription from a licensed prescriber.','bi-clipboard2-pulse',1),
('Over-the-Counter Medicines','over-the-counter-medicines','Medicines you can buy without a prescription.','bi-capsule',2),
('Pain Relief','pain-relief','Analgesics and anti-inflammatories for everyday pain.','bi-bandaid',3),
('Vitamins & Supplements','vitamins-supplements','Vitamins, minerals and dietary supplements.','bi-droplet-half',4),
('Baby Care','baby-care','Infant formula, diapers and baby health essentials.','bi-emoji-smile',5),
('Personal Care','personal-care','Everyday personal hygiene and grooming products.','bi-droplet',6),
('First Aid','first-aid','Dressings, antiseptics and first aid essentials.','bi-shield-plus',7),
('Medical Equipment','medical-equipment','Thermometers, nebulisers, monitors and devices.','bi-activity',8),
('Diabetes Care','diabetes-care','Glucometers, strips and diabetic nutrition.','bi-graph-up-arrow',9),
('Maternal Care','maternal-care','Products for pregnancy, breastfeeding and new mothers.','bi-heart-pulse',10),
('Skin Care','skin-care','Dermatological and cosmetic skin products.','bi-flower1',11),
('Oral Care','oral-care','Toothpaste, mouthwash and dental care.','bi-emoji-laughing',12),
('Sexual & Reproductive Health','sexual-reproductive-health','Contraception and sexual wellness products.','bi-heart',13),
('Health & Wellness','health-wellness','General wellness and healthy-living products.','bi-flower2',14);

SET @c_rx      := (SELECT id FROM categories WHERE slug='prescription-medicines');
SET @c_otc     := (SELECT id FROM categories WHERE slug='over-the-counter-medicines');
SET @c_pain    := (SELECT id FROM categories WHERE slug='pain-relief');
SET @c_vit     := (SELECT id FROM categories WHERE slug='vitamins-supplements');
SET @c_baby    := (SELECT id FROM categories WHERE slug='baby-care');
SET @c_personal:= (SELECT id FROM categories WHERE slug='personal-care');
SET @c_firstaid:= (SELECT id FROM categories WHERE slug='first-aid');
SET @c_equip   := (SELECT id FROM categories WHERE slug='medical-equipment');
SET @c_diab    := (SELECT id FROM categories WHERE slug='diabetes-care');
SET @c_maternal:= (SELECT id FROM categories WHERE slug='maternal-care');
SET @c_skin    := (SELECT id FROM categories WHERE slug='skin-care');
SET @c_oral    := (SELECT id FROM categories WHERE slug='oral-care');
SET @c_srh     := (SELECT id FROM categories WHERE slug='sexual-reproductive-health');
SET @c_wellness:= (SELECT id FROM categories WHERE slug='health-wellness');

INSERT INTO `categories` (`name`,`slug`,`parent_id`,`sort_order`) VALUES
('Analgesics','analgesics',@c_pain,1),
('Antibiotics','antibiotics',@c_rx,1),
('Antihypertensives','antihypertensives',@c_rx,2),
('Cardiovascular','cardiovascular',@c_rx,3),
('Antidiabetics','antidiabetics',@c_diab,1),
('Blood Testing','blood-testing',@c_diab,2),
('Pregnancy & Breastfeeding','pregnancy-breastfeeding',@c_maternal,1),
('Feminine Hygiene','feminine-hygiene',@c_personal,1),
('Contraception','contraception',@c_srh,1);

SET @sc_analgesics := (SELECT id FROM categories WHERE slug='analgesics');
SET @sc_antibiotics:= (SELECT id FROM categories WHERE slug='antibiotics');
SET @sc_antidiab   := (SELECT id FROM categories WHERE slug='antidiabetics');
SET @sc_contraception := (SELECT id FROM categories WHERE slug='contraception');

-- ---------------------------------------------------------------------------
--  BRANDS
-- ---------------------------------------------------------------------------
INSERT INTO `brands` (`name`,`slug`) VALUES
('Emzor','emzor'),('Bayer','bayer'),('Pfizer','pfizer'),('GSK','gsk'),
('Favico','favico'),('Sanochem','sanochem'),('Nestlé Health Science','nestle-health-science'),
('Dettol','dettol'),('Nivea','nivea'),('Omron','omron'),('Avent','avent'),('Always','always');

-- ---------------------------------------------------------------------------
--  PRODUCTS
-- ---------------------------------------------------------------------------
INSERT INTO `products`
(`pharmacy_id`,`sku`,`slug`,`name`,`generic_name`,`brand_id`,`brand_name`,`category_id`,`subcategory_id`,`description`,`active_ingredient`,`strength`,`dosage_form`,`pack_size`,`manufacturer`,`requires_prescription`,`product_class`,`price`,`discount_price`,`tax_rate`,`stock_qty`,`min_stock_level`,`expiry_date`,`batch_number`,`manufacturing_date`,`is_active`,`is_featured`,`rating_avg`,`rating_count`,`sales_count`,`created_at`) VALUES
-- HealthPlus Pharmacy
((SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'HP-0001','paracetamol-500mg-emzor','Emzor Paracetamol 500mg','Paracetamol',(SELECT id FROM brands WHERE slug='emzor'),'Emzor',@c_pain,@sc_analgesics,'Effective relief for mild to moderate pain including headache, fever, toothache and body aches. Trusted by Nigerian families for over 20 years.','Paracetamol (Acetaminophen)','500mg','Tablet','20 tablets','Emzor Pharmaceuticals','0','otc',400.00,320.00,0.00,480,50,'2027-08-31','HP26A441',DATE_SUB(NOW(),INTERVAL 5 MONTH),1,1,4.70,64,820,'2026-02-01 09:00:00'),
((SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'HP-0002','amoxicillin-500mg','Amoxicillin 500mg Capsule','Amoxicillin',(SELECT id FROM brands WHERE slug='sanochem'),'Sanochem',@c_rx,@sc_antibiotics,'Broad-spectrum antibiotic for bacterial infections. Prescription required. Complete the full course as directed by your doctor.','Amoxicillin Trihydrate','500mg','Capsule','21 capsules','Sanochem Nigeria Ltd','1','prescription',1850.00,NULL,0.00,140,30,'2027-03-31','HP26B102',DATE_SUB(NOW(),INTERVAL 8 MONTH),1,0,4.50,41,310,'2026-02-02 09:10:00'),
((SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'HP-0003','vitamin-c-1000mg','Vitamin C 1000mg Effervescent','Ascorbic Acid',(SELECT id FROM brands WHERE slug='favico'),'Favico',@c_vit,NULL,'High-strength effervescent vitamin C tablets for immune support. Dissolve in a glass of water.','Ascorbic Acid','1000mg','Effervescent Tablet','10 tablets','Favico Healthcare',0,'supplement',1200.00,950.00,0.00,265,40,'2027-12-31','HP26C077',DATE_SUB(NOW(),INTERVAL 2 MONTH),1,1,4.60,52,540,'2026-02-03 09:20:00'),
((SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'HP-0004','ors sachets','ORS Rehydration Salts','Oral Rehydration Salts',NULL,'WHO Standard',@c_firstaid,NULL,'WHO-formula oral rehydration salts. Essential for treating diarrhoea and dehydration, especially for children.','Sodium Chloride, Potassium Chloride, Glucose','7.3g per sachet','Powder sachet','50 sachets','Emzor Pharmaceuticals',0,'otc',3400.00,NULL,0.00,95,25,'2028-06-30','HP26D201',DATE_SUB(NOW(),INTERVAL 3 MONTH),1,0,4.90,97,460,'2026-02-04 09:30:00'),
((SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'HP-0005','dettol-liquid-handwash-250ml','Dettol Antibacterial Liquid Handwash','Benzalkonium Chloride',(SELECT id FROM brands WHERE slug='dettol'),'Dettol',@c_personal,NULL,'Antibacterial handwash that kills 99.9% of germs while keeping hands soft. Suitable for daily family use.','Benzalkonium Chloride','250ml','Liquid',NULL,'Reckitt Benckiser',0,'cosmetic',2350.00,2050.00,0.00,168,30,'2028-01-31','HP26E330',DATE_SUB(NOW(),INTERVAL 6 MONTH),1,1,4.70,73,610,'2026-02-05 09:40:00'),
((SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'HP-0006','losartan-50mg','Losartan 50mg Tablet','Losartan Potassium',(SELECT id FROM brands WHERE slug='pfizer'),'Pfizer',@c_rx,@sc_antihypertensives,'ACE receptor blocker used for hypertension and diabetic nephropathy. Prescription required; blood pressure monitoring advised.','Losartan Potassium','50mg','Tablet','30 tablets','Pfizer Nigeria Ltd','1','prescription',4900.00,NULL,0.00,60,20,'2027-11-30','HP26F118',DATE_SUB(NOW(),INTERVAL 4 MONTH),1,0,4.40,28,180,'2026-02-06 09:50:00'),
((SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'HP-0007','infant-formula-1','Aptamil Infant Formula Stage 1','Infant Formula',(SELECT id FROM brands WHERE slug='nestle-health-science'),'Nestlé',@c_baby,NULL,'Stage 1 infant formula suitable from birth to 6 months, with DHA, ARA and prebiotic nucleotides.','Milk Protein, DHA, ARA','400g','Powder',NULL,'Nestlé Nigeria',0,'supplement',14800.00,13500.00,0.00,32,10,'2027-09-30','HP26G088',DATE_SUB(NOW(),INTERVAL 1 MONTH),1,1,4.80,45,95,'2026-02-07 10:00:00'),
((SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'HP-0008','ibuprofen-400mg','Ibuprofen 400mg Tablet','Ibuprofen',(SELECT id FROM brands WHERE slug='bayer'),'Bayer',@c_pain,@sc_analgesics,'Anti-inflammatory analgesic for pain, fever and inflammation. Take with food to reduce stomach upset.','Ibuprofen','400mg','Tablet','20 tablets','Bayer Nigeria',0,'otc',780.00,650.00,0.00,410,50,'2026-11-30','HP26H014',DATE_SUB(NOW(),INTERVAL 9 MONTH),1,0,4.50,59,730,'2026-02-08 10:10:00'),

-- MediCare Pharmacy Lekki
((SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'MC-0001','metformin-850mg','Metformin 850mg Tablet','Metformin HCl',(SELECT id FROM brands WHERE slug='emzor'),'Emzor',@c_diab,@sc_antidiab,'First-line medicine for type 2 diabetes. Prescription required. Monitor blood sugar as advised by your doctor.','Metformin Hydrochloride','850mg','Tablet','30 tablets','Emzor Pharmaceuticals','1','prescription',2200.00,NULL,0.00,175,40,'2028-02-29','MC26A501',DATE_SUB(NOW(),INTERVAL 4 MONTH),1,1,4.60,66,420,'2026-02-01 11:00:00'),
((SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'MC-0002','glucometer-one-touch','OneTouch Verio Glucometer Kit','Glucose Monitoring Device',(SELECT id FROM brands WHERE slug='omron'),'OneTouch',@c_diab,@sc_antidiab,'Complete blood glucose monitoring starter kit including meter, 10 test strips, lancets and carry case.','Glucose Oxidase',NULL,'Device',NULL,'Johnson & Johnson',0,'device',28500.00,24900.00,0.00,24,8,'2029-12-31','MC26B220',DATE_SUB(NOW(),INTERVAL 2 MONTH),1,1,4.90,38,64,'2026-02-02 11:10:00'),
((SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'MC-0003','folic-acid-5mg','Folic Acid 5mg Tablets','Folic Acid',(SELECT id FROM brands WHERE slug='favico'),'Favico',@c_maternal,NULL,'Essential supplement recommended before and during pregnancy to support neural tube development.','Folic Acid','5mg','Tablet','30 tablets','Favico Healthcare',0,'supplement',1650.00,1400.00,0.00,220,35,'2028-05-31','MC26C330',DATE_SUB(NOW(),INTERVAL 5 MONTH),1,0,4.50,44,390,'2026-02-03 11:20:00'),
((SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'MC-0004','insulin-glargine','Insulin Glargine 100IU/ml','Insulin Glargine',(SELECT id FROM brands WHERE slug='sanofi'),'Sanofi',@c_diab,@sc_antidiab,'Long-acting insulin analogue for type 1 and type 2 diabetes. Cold-chain product — must be refrigerated at 2–8°C. Prescription required.','Insulin Glargine','100IU/ml','Injection','3 x 3ml pens','Sanofi Aventis',1,'prescription',38500.00,NULL,0.00,18,6,'2026-10-31','MC26D112',DATE_SUB(NOW(),INTERVAL 2 MONTH),1,1,4.90,22,31,'2026-02-04 11:30:00'),
((SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'MC-0005','semenax-male-enhancer','Semenax Male Vitality Capsules','Herbal Blend',NULL,'MediCare',@c_srh,@sc_contraception,'Herbal dietary supplement supporting male vitality and stamina. Not a substitute for medical advice.','Herbal Blend','500mg','Capsule','30 capsules','MediCare Lekki',0,'supplement',9500.00,7900.00,0.00,42,12,'2027-08-31','MC26E640',DATE_SUB(NOW(),INTERVAL 3 MONTH),1,0,4.20,29,72,'2026-02-05 11:40:00'),
((SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'MC-0006','thermometer-digital','Digital Clinical Thermometer','Digital Thermometer',(SELECT id FROM brands WHERE slug='omron'),'Omron',@c_equip,NULL,'Fast-reading digital thermometer with fever alarm, suitable for oral and axillary use.','NTC Thermistor',NULL,'Device',NULL,'Omron Healthcare',0,'device',3200.00,2750.00,0.00,85,20,'2029-06-30','MC26F455',DATE_SUB(NOW(),INTERVAL 6 MONTH),1,0,4.50,51,210,'2026-02-06 11:50:00');

SET @p_para := (SELECT id FROM products WHERE slug='paracetamol-500mg-emzor');
SET @p_amox := (SELECT id FROM products WHERE slug='amoxicillin-500mg');
SET @p_vitc := (SELECT id FROM products WHERE slug='vitamin-c-1000mg');
SET @p_ors  := (SELECT id FROM products WHERE slug='ors-sachets');
SET @p_dettol := (SELECT id FROM products WHERE slug='dettol-liquid-handwash-250ml');
SET @p_met  := (SELECT id FROM products WHERE slug='metformin-850mg');
SET @p_gluco:= (SELECT id FROM products WHERE slug='glucometer-one-touch');
SET @p_folic := (SELECT id FROM products WHERE slug='folic-acid-5mg');
SET @p_formula := (SELECT id FROM products WHERE slug='infant-formula-1');

-- Primary images (placeholder path; swap with real uploads)
INSERT INTO `product_images` (`product_id`,`file_path`,`is_primary`,`sort_order`) VALUES
(@p_para,  'assets/images/products/placeholder-1.svg',1,0),
(@p_amox,  'assets/images/products/placeholder-2.svg',1,0),
(@p_vitc,  'assets/images/products/placeholder-3.svg',1,0),
(@p_ors,   'assets/images/products/placeholder-4.svg',1,0),
(@p_dettol,'assets/images/products/placeholder-5.svg',1,0),
(@p_met,   'assets/images/products/placeholder-6.svg',1,0),
(@p_gluco, 'assets/images/products/placeholder-7.svg',1,0),
(@p_folic, 'assets/images/products/placeholder-8.svg',1,0),
(@p_formula,'assets/images/products/placeholder-9.svg',1,0);

INSERT INTO `product_batches` (`product_id`,`batch_number`,`manufacturing_date`,`expiry_date`,`quantity`,`cost_price`) VALUES
(@p_para,'HP26A441',DATE_SUB(NOW(),INTERVAL 5 MONTH),'2027-08-31',300,220.00),
(@p_para,'HP25A990',DATE_SUB(NOW(),INTERVAL 17 MONTH),'2026-09-30',180,215.00),
(@p_vitc,'HP26C077',DATE_SUB(NOW(),INTERVAL 2 MONTH),'2027-12-31',265,640.00),
(@p_amox,'HP26B102',DATE_SUB(NOW(),INTERVAL 8 MONTH),'2027-03-31',140,1200.00);

INSERT INTO `inventory_movements` (`product_id`,`pharmacy_id`,`type`,`previous_qty`,`change_qty`,`new_qty`,`reason`,`user_id`,`created_at`) VALUES
(@p_para,(SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'purchase',0,480,480,'Opening stock intake',(SELECT id FROM users WHERE email='staff@healthplus.ng'),'2026-02-01 08:00:00'),
(@p_para,(SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'sale',480,-12,468,'Order IPL-20260918-4A1C09',(SELECT id FROM users WHERE email='staff@healthplus.ng'),'2026-09-18 11:20:00'),
(@p_amox,(SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'purchase',0,140,140,'Opening stock intake',(SELECT id FROM users WHERE email='staff@healthplus.ng'),'2026-02-02 08:00:00'),
(@p_met ,(SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'purchase',0,175,175,'Opening stock intake',(SELECT id FROM users WHERE email='owner@medicare.ng'),'2026-02-01 10:00:00');

-- ---------------------------------------------------------------------------
--  PLATFORM SETTINGS
-- ---------------------------------------------------------------------------
INSERT INTO `platform_settings` (`group_name`,`key_name`,`value`,`is_secret`) VALUES
('general','platform_name','iPharmaLink',0),
('general','tagline','Your Trusted Pharmacy, Delivered',0),
('general','logo','assets/images/logo.svg',0),
('general','favicon','assets/images/favicon.svg',0),
('general','support_email','support@ipharmalink.ng',0),
('general','support_phone','+234 800 000 0000',0),
('general','support_address','14 Adeola Odeku Street, Victoria Island, Lagos',0),
('currency','currency_code','NGN',0),
('currency','currency_symbol','&#8358;',0),
('currency','currency_position','before',0),
('currency','decimal_places','2',0),
('payment','default_gateway','paystack',0),
('payment','paystack_enabled','1',0),
('payment','flutterwave_enabled','0',0),
('payment','cash_on_delivery_enabled','1',0),
('payment','bank_transfer_enabled','1',0),
('delivery','default_delivery_fee','1500',0),
('delivery','free_delivery_threshold','30000',0),
('delivery','default_eta_minutes','60',0),
('commission','default_rate_percent','8.00',0),
('commission','default_fixed','0',0),
('commission','payout_minimum','20000',0),
('commission','payout_release','on_delivery',0),
('security','password_min_length','8',0),
('security','max_login_attempts','5',0),
('security','lockout_minutes','15',0),
('security','session_idle_timeout','3600',0),
('security','require_email_verification','0',0),
('security','two_factor_enabled','0',0),
('system','timezone','Africa/Lagos',0),
('system','date_format','d M Y',0),
('system','pagination_per_page','24',0),
('system','max_upload_size_mb','5',0),
('system','maintenance_mode','0',0),
('system','low_stock_threshold_default','10',0),
('system','expiry_alert_days','90',0);

INSERT INTO `payment_gateways` (`code`,`name`,`is_enabled`,`is_sandbox`,`sort_order`) VALUES
('paystack','Paystack',1,1,1),
('flutterwave','Flutterwave',0,1,2),
('bank_transfer','Bank Transfer',1,0,3),
('cash_on_delivery','Cash on Delivery',1,0,4);

-- ---------------------------------------------------------------------------
--  COUPONS
-- ---------------------------------------------------------------------------
INSERT INTO `coupons` (`code`,`description`,`type`,`value`,`min_order_value`,`max_discount`,`usage_limit`,`per_user_limit`,`starts_at`,`expires_at`) VALUES
('WELCOME10','10% off your first order','percent',10.00,5000.00,5000.00,5000,1,DATE_SUB(NOW(),INTERVAL 30 DAY),DATE_ADD(NOW(),INTERVAL 90 DAY)),
('DELIVERYFREE','Free delivery on orders above ₦5,000','fixed',2000.00,5000.00,2000.00,NULL,5,DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_ADD(NOW(),INTERVAL 60 DAY)),
('VITAMIN20','20% off vitamins & supplements','percent',20.00,10000.00,8000.00,1000,3,DATE_SUB(NOW(),INTERVAL 5 DAY),DATE_ADD(NOW(),INTERVAL 45 DAY));

-- ---------------------------------------------------------------------------
--  CMS CONTENT
-- ---------------------------------------------------------------------------
INSERT INTO `pages` (`slug`,`title`,`meta_title`,`meta_description`,`body`,`is_published`) VALUES
('about','About iPharmaLink','About iPharmaLink | Nigeria''s Pharmacy Marketplace','iPharmaLink connects Nigerians with verified pharmacies for genuine medicines, delivered or ready for pickup.','<p>iPharmaLink is a multi-pharmacy marketplace that connects Nigerian customers with registered, verified pharmacies across the country. We exist to reduce unnecessary trips to the pharmacy, especially for chronic medications and everyday health essentials.</p><h3>What we stand for</h3><ul><li>Genuine, NAFDAC-approved products only</li><li>Registered and verified pharmacies</li><li>Transparent pricing with no hidden charges</li><li>Prescription medicines dispensed only after pharmacist review</li></ul>',1),
('terms','Terms and Conditions','Terms and Conditions | iPharmaLink','Terms governing the use of the iPharmaLink platform.','<p>By using iPharmaLink you agree to these terms. All orders are subject to product availability at the selected pharmacy. Prescription-only medicines require a valid prescription and pharmacist approval before dispensing.</p>',1),
('privacy','Privacy Policy','Privacy Policy | iPharmaLink','How iPharmaLink collects, uses and protects your personal data.','<p>We collect only the information needed to fulfil your orders: name, contact details and delivery address. Payment card details are handled entirely by certified payment gateways and never stored on our servers.</p>',1),
('delivery-policy','Delivery Policy','Delivery Policy | iPharmaLink','How iPharmaLink pharmacy delivery and pickup work.','<p>Delivery is available within each pharmacy''s stated service radius. Orders are dispatched after the pharmacy confirms preparation. Customers may also choose pharmacy pickup at checkout. Delivery fees and estimated times are shown before payment.</p>',1),
('refund-policy','Refund Policy','Refund Policy | iPharmaLink','Conditions for refunds, returns and order cancellations.','<p>Refunds are processed for cancelled or unavailable orders and for eligible quality complaints raised within 48 hours of delivery. Refunds are returned to the original payment method within 3–7 business days.</p>',1),
('pharmacy-terms','Pharmacy Vendor Terms','Pharmacy Terms | iPharmaLink','Terms for pharmacies selling on iPharmaLink.','<p>Registered pharmacies must hold a valid Pharmacy Council of Nigeria licence, list only genuine approved products, honour listed prices, and dispense prescription medicines only after pharmacist verification. A platform commission is deducted from each settled order.</p>',1);

INSERT INTO `banners` (`title`,`subtitle`,`image`,`button_text`,`button_link`,`placement`,`sort_order`,`is_active`) VALUES
('Skip the Queue, Not Your Health','Order medicines from verified pharmacies and have them delivered in under 60 minutes across Lagos.','assets/images/banners/slide-1.svg','Shop Medicines','/products','home_slider',1,1),
('Refill Your Prescriptions','Chronic condition medicines delivered to your door, on schedule. Prescription verification included.','assets/images/banners/slide-2.svg','Browse Prescriptions','/categories','home_slider',2,1),
('Vitamins, Wellness & Baby Care','Authentic supplements and baby essentials from trusted pharmacies. Genuine products, honest prices.','assets/images/banners/slide-3.svg','Explore Deals','/search?q=vitamin','home_slider',3,1);

INSERT INTO `faqs` (`category`,`question`,`answer`,`sort_order`) VALUES
('Orders','How do I place an order?','Add medicines to your cart from one or more pharmacies, proceed to checkout, choose delivery or pickup, complete payment, and track your order from My Orders.',1),
('Orders','Can I buy from multiple pharmacies in one checkout?','Yes. Your single checkout is automatically split into a separate sub-order for each pharmacy. Each pharmacy only sees and processes its own portion.',2),
('Payments','Which payment methods are supported?','Paystack, Flutterwave, bank transfer and cash on delivery, depending on the pharmacies in your order. Available gateways are shown at checkout.',3),
('Payments','How do I know my payment was successful?','Payment is confirmed only after verification with the payment gateway. You will see your order move to Paid and receive a confirmation notification.',4),
('Prescriptions','Do I need a prescription to buy antibiotics?','Yes. Medicines marked Prescription Required cannot be purchased without a valid prescription. Upload it at checkout and the pharmacist will review it before dispensing.',5),
('Prescriptions','Who approves my prescription?','A licensed pharmacist at the pharmacy fulfilling your order reviews the prescription and approves or rejects it. You will be notified of the decision.',6),
('Delivery','How long does delivery take?','Most pharmacies in Lagos deliver within 30–90 minutes. The exact estimate for your address is shown at checkout before you pay.',7),
('Delivery','Can I collect my order instead?','Yes. Select Pharmacy Pickup at checkout and collect from the pharmacy address at no delivery charge.',8),
('Account','How do I reset my password?','Use the Forgot Password link on the login page. We will email you a secure, time-limited reset link.',9),
('Account','How is my account protected?','Passwords are hashed with bcrypt, sessions are secured and regenerated on login, all forms carry CSRF tokens, and sensitive actions are recorded in our audit log.',10);

-- ---------------------------------------------------------------------------
--  DEMO ORDERS  (multi-pharmacy split demonstration)
-- ---------------------------------------------------------------------------
SET @cust1 := (SELECT id FROM users WHERE email='customer@demo.ng');
SET @cust2 := (SELECT id FROM users WHERE email='blessing@demo.ng');
SET @addr1 := (SELECT id FROM user_addresses WHERE user_id=@cust1 AND label='Home');

INSERT INTO `orders` (`order_number`,`customer_id`,`status`,`payment_status`,`fulfilment_method`,`delivery_address_id`,`address_snapshot`,`delivery_fee`,`discount_total`,`tax_total`,`subtotal`,`total`,`currency`,`customer_note`,`placed_at`,`paid_at`,`completed_at`,`created_at`) VALUES
('IPL-20260918-4A1C09',@cust1,'delivered','successful','delivery',@addr1,
 '{"recipient_name":"Tunde Adeyemi","phone":"+2348044440001","state":"Lagos","city":"Ikeja","address_line":"14 Obafemi Awolowo Way, Ikeja","landmark":"Opposite Ikeja City Mall"}',
 0.00,320.00,0.00,1080.00,760.00,'NGN','Please call when you arrive.',DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 9 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY)),

('IPL-20260925-7B2D14',@cust1,'processing','successful','delivery',@addr1,
 '{"recipient_name":"Tunde Adeyemi","phone":"+2348044440001","state":"Lagos","city":"Ikeja","address_line":"14 Obafemi Awolowo Way, Ikeja","landmark":"Opposite Ikeja City Mall"}',
 0.00,0.00,0.00,5225.00,5225.00,'NGN','Deliver to the security post.',DATE_SUB(NOW(),INTERVAL 3 DAY),DATE_SUB(NOW(),INTERVAL 3 DAY),NULL,DATE_SUB(NOW(),INTERVAL 3 DAY)),

('IPL-20260926-9C3E21',@cust2,'pending_payment','pending','pickup',NULL,
 '{"recipient_name":"Blessing Adeyinka","phone":"+2348044440002","state":"Lagos","city":"Surulere","address_line":"3 Bode Thomas Street, Surulere","landmark":"Behind Trade Fair"}',
 0.00,0.00,0.00,5400.00,5400.00,'NGN','I will collect on Saturday.',NULL,NULL,NULL,DATE_SUB(NOW(),INTERVAL 2 DAY));

SET @ord1 := (SELECT id FROM orders WHERE order_number='IPL-20260918-4A1C09');
SET @ord2 := (SELECT id FROM orders WHERE order_number='IPL-20260925-7B2D14');
SET @ord3 := (SELECT id FROM orders WHERE order_number='IPL-20260926-9C3E21');

INSERT INTO `order_items` (`order_id`,`product_id`,`pharmacy_id`,`product_name`,`sku`,`image`,`unit_price`,`quantity`,`discount`,`tax_rate`,`tax_amount`,`line_total`,`requires_prescription`,`prescription_status`,`status`)
SELECT @ord1,@p_para,(SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'Emzor Paracetamol 500mg','HP-0001','assets/images/products/placeholder-1.svg',400.00,3,320.00,0.00,0.00,880.00,0,'not_required','delivered';
INSERT INTO `order_items` (`order_id`,`product_id`,`pharmacy_id`,`product_name`,`sku`,`image`,`unit_price`,`quantity`,`discount`,`tax_rate`,`tax_amount`,`line_total`,`requires_prescription`,`prescription_status`,`status`)
SELECT @ord1,@p_met,(SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'Metformin 850mg Tablet','MC-0001','assets/images/products/placeholder-6.svg',2200.00,1,0.00,0.00,0.00,2200.00,1,'approved','delivered';

-- NOTE: recompute order 1 totals to be internally consistent
UPDATE `orders` o SET o.subtotal = (SELECT SUM(line_total) FROM order_items WHERE order_id=o.id)
WHERE o.id = @ord1;
UPDATE `orders` o SET o.total = o.subtotal - o.discount_total + o.delivery_fee + o.tax_total
WHERE o.id = @ord1;

INSERT INTO `order_items` (`order_id`,`product_id`,`pharmacy_id`,`product_name`,`sku`,`image`,`unit_price`,`quantity`,`discount`,`tax_rate`,`tax_amount`,`line_total`,`requires_prescription`,`prescription_status`,`status`)
SELECT @ord2,@p_ors,(SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'ORS Rehydration Salts','HP-0004','assets/images/products/placeholder-4.svg',3400.00,1,0.00,0.00,0.00,3400.00,0,'not_required','delivered';
INSERT INTO `order_items` (`order_id`,`product_id`,`pharmacy_id`,`product_name`,`sku`,`image`,`unit_price`,`quantity`,`discount`,`tax_rate`,`tax_amount`,`line_total`,`requires_prescription`,`prescription_status`,`status`)
SELECT @ord2,@p_gluco,(SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'OneTouch Verio Glucometer Kit','MC-0002','assets/images/products/placeholder-7.svg',28500.00,1,3600.00,0.00,0.00,24900.00,0,'not_required','preparing';
INSERT INTO `order_items` (`order_id`,`product_id`,`pharmacy_id`,`product_name`,`sku`,`image`,`unit_price`,`quantity`,`discount`,`tax_rate`,`tax_amount`,`line_total`,`requires_prescription`,`prescription_status`,`status`)
SELECT @ord2,@p_dettol,(SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'Dettol Antibacterial Liquid Handwash','HP-0005','assets/images/products/placeholder-5.svg',2350.00,1,0.00,0.00,0.00,2350.00,0,'not_required','delivered';

INSERT INTO `order_items` (`order_id`,`product_id`,`pharmacy_id`,`product_name`,`sku`,`image`,`unit_price`,`quantity`,`discount`,`tax_rate`,`tax_amount`,`line_total`,`requires_prescription`,`prescription_status`,`status`)
SELECT @ord3,@p_formula,(SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),'Aptamil Infant Formula Stage 1','HP-0007','assets/images/products/placeholder-9.svg',14800.00,1,0.00,0.00,0.00,13500.00,0,'not_required','pending';
INSERT INTO `order_items` (`order_id`,`product_id`,`pharmacy_id`,`product_name`,`sku`,`image`,`unit_price`,`quantity`,`discount`,`tax_rate`,`tax_amount`,`line_total`,`requires_prescription`,`prescription_status`,`status`)
SELECT @ord3,@p_folic,(SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),'Folic Acid 5mg Tablets','MC-0003','assets/images/products/placeholder-8.svg',1650.00,1,0.00,0.00,0.00,1400.00,0,'not_required','pending';

INSERT INTO `pharmacy_orders` (`order_id`,`pharmacy_id`,`sub_order_number`,`status`,`items_subtotal`,`delivery_fee`,`tax_total`,`discount_total`,`platform_fee`,`pharmacy_earnings`,`total`,`pharmacy_note`,`accepted_at`,`ready_at`,`delivered_at`,`created_at`)
SELECT @ord1,id,'IPL-20260918-4A1C09-A','delivered',880.00,0.00,0.00,320.00,44.80,515.20,560.00,'Packed and handed to rider.',DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 9 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY) FROM pharmacies WHERE slug='healthplus-pharmacy';
INSERT INTO `pharmacy_orders` (`order_id`,`pharmacy_id`,`sub_order_number`,`status`,`items_subtotal`,`delivery_fee`,`tax_total`,`discount_total`,`platform_fee`,`pharmacy_earnings`,`total`,`pharmacy_note`,`accepted_at`,`ready_at`,`delivered_at`,`created_at`)
SELECT @ord1,id,'IPL-20260918-4A1C09-B','delivered',2200.00,0.00,0.00,0.00,110.00,2090.00,2200.00,'Prescription verified by pharmacist.',DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 9 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY) FROM pharmacies WHERE slug='medicare-pharmacy-lekki';

INSERT INTO `pharmacy_orders` (`order_id`,`pharmacy_id`,`sub_order_number`,`status`,`items_subtotal`,`delivery_fee`,`tax_total`,`discount_total`,`platform_fee`,`pharmacy_earnings`,`total`,`pharmacy_note`,`accepted_at`,`ready_at`,`created_at`)
SELECT @ord2,id,'IPL-20260925-7B2D14-A','preparing',5750.00,0.00,0.00,0.00,287.50,5462.50,5750.00,'Being prepared.',DATE_SUB(NOW(),INTERVAL 3 DAY),NULL,DATE_SUB(NOW(),INTERVAL 3 DAY) FROM pharmacies WHERE slug='healthplus-pharmacy';
INSERT INTO `pharmacy_orders` (`order_id`,`pharmacy_id`,`sub_order_number`,`status`,`items_subtotal`,`delivery_fee`,`tax_total`,`discount_total`,`platform_fee`,`pharmacy_earnings`,`total`,`pharmacy_note`,`accepted_at`,`created_at`)
SELECT @ord2,id,'IPL-20260925-7B2D14-B','paid',24900.00,0.00,0.00,0.00,0.00,24900.00,24900.00,NULL,NULL,DATE_SUB(NOW(),INTERVAL 3 DAY) FROM pharmacies WHERE slug='medicare-pharmacy-lekki';

INSERT INTO `pharmacy_orders` (`order_id`,`pharmacy_id`,`sub_order_number`,`status`,`items_subtotal`,`delivery_fee`,`tax_total`,`discount_total`,`platform_fee`,`pharmacy_earnings`,`total`,`created_at`)
SELECT @ord3,id,'IPL-20260926-9C3E21-A','pending_payment',13500.00,0.00,0.00,0.00,0.00,0.00,13500.00,DATE_SUB(NOW(),INTERVAL 2 DAY) FROM pharmacies WHERE slug='healthplus-pharmacy';
INSERT INTO `pharmacy_orders` (`order_id`,`pharmacy_id`,`sub_order_number`,`status`,`items_subtotal`,`delivery_fee`,`tax_total`,`discount_total`,`platform_fee`,`pharmacy_earnings`,`total`,`created_at`)
SELECT @ord3,id,'IPL-20260926-9C3E21-B','pending_payment',1400.00,0.00,0.00,0.00,0.00,0.00,1400.00,DATE_SUB(NOW(),INTERVAL 2 DAY) FROM pharmacies WHERE slug='medicare-pharmacy-lekki';

INSERT INTO `order_status_history` (`order_id`,`scope`,`scope_id`,`from_status`,`to_status`,`note`,`actor_id`,`actor_role`,`created_at`) VALUES
(@ord1,'order',@ord1,'pending_payment','paid','Payment confirmed via Paystack',@cust1,'customer',DATE_SUB(NOW(),INTERVAL 10 DAY)),
(@ord1,'order',@ord1,'paid','delivered','All sub-orders delivered',@cust1,'customer',DATE_SUB(NOW(),INTERVAL 9 DAY)),
(@ord2,'order',@ord2,'pending_payment','paid','Payment confirmed via Paystack',@cust1,'customer',DATE_SUB(NOW(),INTERVAL 3 DAY)),
(@ord2,'order',@ord2,'paid','processing','Pharmacies preparing orders',NULL,'system',DATE_SUB(NOW(),INTERVAL 3 DAY)),
(@ord3,'order',@ord3,'pending_payment','pending_payment','Awaiting payment',NULL,'system',DATE_SUB(NOW(),INTERVAL 2 DAY));

INSERT INTO `payments` (`order_id`,`customer_id`,`gateway_code`,`amount`,`currency`,`status`,`reference`,`paid_at`,`created_at`) VALUES
(@ord1,@cust1,'paystack',760.00,'NGN','successful','PSK-'||@ord1,DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY)),
(@ord2,@cust1,'paystack',5225.00,'NGN','successful','PSK-'||@ord2,DATE_SUB(NOW(),INTERVAL 3 DAY),DATE_SUB(NOW(),INTERVAL 3 DAY)),
(@ord3,@cust2,'paystack',5400.00,'NGN','pending','PSK-'||@ord3,NULL,DATE_SUB(NOW(),INTERVAL 2 DAY));

INSERT INTO `commissions` (`pharmacy_order_id`,`order_id`,`pharmacy_id`,`base_amount`,`rate_percent`,`fixed_amount`,`commission_amount`,`pharmacy_earnings`,`status`,`created_at`)
SELECT po.id,po.order_id,po.pharmacy_id,po.items_subtotal,8.00,0.00,po.platform_fee,po.pharmacy_earnings,'credited',po.created_at
FROM pharmacy_orders po WHERE po.status='delivered';

INSERT INTO `wallet_transactions` (`wallet_id`,`pharmacy_id`,`type`,`amount`,`balance_after`,`description`,`reference_type`,`reference_id`,`created_at`)
SELECT w.id,w.pharmacy_id,'credit',po.pharmacy_earnings,po.pharmacy_earnings,'Earnings from '||po.sub_order_number,'pharmacy_order',po.id,po.created_at
FROM pharmacy_orders po JOIN pharmacy_wallets w ON w.pharmacy_id=po.pharmacy_id
WHERE po.status='delivered';

UPDATE pharmacy_wallets w SET
  w.balance      = (SELECT COALESCE(SUM(amount),0) FROM wallet_transactions t WHERE t.wallet_id=w.id AND t.type='credit'),
  w.total_earned = w.balance,
  w.pending_balance = (SELECT COALESCE(SUM(pharmacy_earnings),0) FROM pharmacy_orders po WHERE po.pharmacy_id=w.pharmacy_id AND po.status IN ('paid','processing','preparing','ready_for_pickup','ready_for_delivery','out_for_delivery'))
WHERE EXISTS (SELECT 1 FROM wallet_transactions t WHERE t.wallet_id=w.id);

INSERT INTO `deliveries` (`order_id`,`pharmacy_order_id`,`pharmacy_id`,`personnel_id`,`tracking_number`,`status`,`pickup_address`,`dropoff_address`,`pickup_latitude`,`pickup_longitude`,`dropoff_latitude`,`dropoff_longitude`,`delivery_fee`,`otp_code`,`delivery_note`,`assigned_at`,`picked_up_at`,`delivered_at`,`confirmed_by_customer_at`,`created_at`)
SELECT @ord1,po.id,po.pharmacy_id,(SELECT id FROM users WHERE email='rider@demo.ng'),
 CONCAT('IPD-',SUBSTRING(MD5(po.id),1,8)),'delivered',
 '14 Obafemi Awolowo Way, Ikeja', '14 Obafemi Awolowo Way, Ikeja (opposite Ikeja City Mall)',
 6.6018,3.3515,6.6018,3.3515, 0.00,'481302','Delivered and confirmed by customer.',
 DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 9 DAY),DATE_SUB(NOW(),INTERVAL 9 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY)
FROM pharmacy_orders po WHERE po.order_id=@ord1;

INSERT INTO `reviews` (`user_id`,`entity_type`,`product_id`,`pharmacy_id`,`order_id`,`rating`,`title`,`body`,`status`,`created_at`) VALUES
(@cust1,'product',@p_para,(SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),@ord1,5,'Fast and genuine','Arrived within 40 minutes and the product is original. Pharmacy staff confirmed the dosage for me.','published',DATE_SUB(NOW(),INTERVAL 9 DAY)),
(@cust1,'product',@p_met,(SELECT id FROM pharmacies WHERE slug='medicare-pharmacy-lekki'),@ord1,4,'Good service','Prescription was reviewed quickly. Slight wait for the pharmacist.','published',DATE_SUB(NOW(),INTERVAL 9 DAY)),
(@cust1,'pharmacy',NULL,(SELECT id FROM pharmacies WHERE slug='healthplus-pharmacy'),@ord1,5,'Excellent neighbourhood pharmacy','Great service every time. Prices are clearly displayed and delivery is quick.','published',DATE_SUB(NOW(),INTERVAL 9 DAY));

INSERT INTO `wishlists` (`user_id`,`name`) VALUES (@cust1,'My Wishlist');
INSERT INTO `wishlist_items` (`wishlist_id`,`product_id`)
SELECT w.id,@p_vitc FROM wishlists w WHERE w.user_id=@cust1;

INSERT INTO `notifications` (`user_id`,`type`,`title`,`body`,`link`,`channel`,`is_read`,`created_at`) VALUES
(@cust1,'order.paid','Payment received','Your payment of ₦5,225.00 for order IPL-20260925-7B2D14 was successful.','/account/orders/2','inapp',1,DATE_SUB(NOW(),INTERVAL 3 DAY)),
(@cust1,'order.delivered','Order delivered','Your order IPL-20260918-4A1C09 has been delivered. Rate your experience.','/account/orders/1','inapp',1,DATE_SUB(NOW(),INTERVAL 9 DAY)),
(@cust2,'order.pending','Payment pending','Complete payment for order IPL-20260926-9C3E21 to confirm your order.','/account/orders/3','inapp',0,DATE_SUB(NOW(),INTERVAL 2 DAY));

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
--  POST-LOAD FIXUPS
--  The password hashes below must be replaced with real bcrypt hashes of
--  "Password123!". They are generated at install time by:
--      php database/hash.php
--  See README → Demo Credentials.
-- ============================================================================

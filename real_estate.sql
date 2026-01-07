-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Май 27 2025 г., 01:07
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `real_estate`
--

-- --------------------------------------------------------

--
-- Структура таблицы `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `source` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `news`
--

INSERT INTO `news` (`id`, `title`, `text`, `photo`, `date`, `source`, `created_at`) VALUES
(1, 'New Office Opening Soon!', 'We are excited to announce the grand opening of our new branch office next month. More details to come!', 'images/news/news_office.jpg', '2024-03-10', 'Company News', '2025-05-18 20:12:08'),
(2, 'Real Estate Market Trends Q1 2024', 'The first quarter of 2024 shows promising trends in the local real estate market. Click to read the full report.', 'images/news/news_trends.jpg', '2024-03-05', 'Market Analysis', '2025-05-18 20:12:08'),
(3, 'Community Charity Drive Success', 'Thank you to everyone who participated in our recent community charity drive. We raised over $5000 for local schools!', NULL, '2024-02-28', 'Community', '2025-05-18 20:12:08'),
(4, 'The Verdant Spire: City\'s Newest Architectural Icon Nears Completion, Redefining Urban Living', 'The city skyline is on the cusp of a dramatic transformation with The Verdant Spire, a groundbreaking mixed-use development, rapidly approaching its grand unveiling. Situated in the heart of the revitalized downtown district, this architectural marvel is more than just a building; it\'s a bold statement about the future of sustainable urban living. Designed by the internationally acclaimed firm \"EcoStructures Inc.,\" The Verdant Spire boasts 60 stories of innovative design, integrating residential, commercial, and public green spaces in a way never before seen in our metropolis.\r\n\"Our vision for The Verdant Spire was to create a living, breathing ecosystem within the urban fabric,\" says Lead Architect, Isabella Chen. \"We didn\'t just want to build upwards; we wanted to build smarter and greener.\" The tower features a unique bio-climatic facade, with self-adjusting louvers that optimize natural light انتخابات and reduce solar gain, significantly lowering energy consumption. Cascading vertical gardens and expansive sky terraces are not just aesthetically pleasing but also contribute to a natural cooling effect and provide residents with serene green oases amidst the city\'s hustle.\r\nThe residential component offers a range of luxury apartments, from spacious one-bedroom units to opulent penthouses, all equipped with state-of-the-art smart home technology and panoramic city views. Residents will have exclusive access to a sky lounge, an Olympic-sized infinity pool, a high-tech fitness center, and dedicated co-working spaces. The lower floors are dedicated to premium retail outlets, gourmet restaurants, and a contemporary art gallery, aiming to create a vibrant community hub accessible to both residents and the public. The project also includes a significant public park at its base, seamlessly integrating the tower with the surrounding neighborhood. Local officials have lauded the project for its commitment to sustainability, job creation during its construction phase, and its potential to attract further investment into the downtown core. The final touches are now being put in place, with a ribbon-cutting ceremony ожидается to take place in early Autumn.', 'images/news/newsimg_682bb7506c6553.58707498.jpg', '2025-05-20', 'Urban Development Monthly', '2025-05-19 22:57:20');

-- --------------------------------------------------------

--
-- Структура таблицы `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
  `mls_number` varchar(50) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `property_type` varchar(50) DEFAULT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` decimal(3,1) DEFAULT NULL,
  `area_sqm` int(11) DEFAULT NULL,
  `lot_size_sqm` int(11) DEFAULT NULL,
  `year_built` year(4) DEFAULT NULL,
  `listing_type` enum('sale','rent') NOT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `agent_id` int(11) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `date_listed` date NOT NULL,
  `date_available` date DEFAULT NULL,
  `status` enum('active','pending','purchased','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `properties`
--

INSERT INTO `properties` (`id`, `mls_number`, `title`, `description`, `address_line1`, `address_line2`, `city`, `state`, `zip_code`, `latitude`, `longitude`, `property_type`, `bedrooms`, `bathrooms`, `area_sqm`, `lot_size_sqm`, `year_built`, `listing_type`, `price`, `currency`, `agent_id`, `contact_name`, `contact_email`, `contact_phone`, `date_listed`, `date_available`, `status`, `created_at`, `updated_at`) VALUES
(2, NULL, 'Apartment in the center of Kiev', 'One-room apartment near the golden gate with good renovation. The apartment is ready to move in now. There is air conditioning and excellent noise insulation.', 'Bohdan Khmelnytsky Street, 52', NULL, 'Kiev', 'Kiev region', '02000', 50.44740231, 30.50644755, 'Apartment', 1, 1.0, NULL, NULL, NULL, 'sale', 105000.00, 'USD', 1, 'Adminstrator', 'sftype1@gmail.com', NULL, '2025-05-20', NULL, 'active', '2025-05-20 19:22:05', '2025-05-22 22:37:37'),
(3, NULL, 'Apartment in the center of Kiev 2 Bedrooms', 'Two-room apartment near the golden gate with good renovation. The apartment is ready to move in now. There is air conditioning and excellent noise insulation.', 'Oleksy Tykhoho Street, 87', NULL, 'Kiev', 'Kiev region', '02000', 50.44999327, 30.43267608, 'Apartment', 2, 1.0, 97, NULL, NULL, 'sale', 142000.00, 'USD', 1, 'Administrator', 'sftype1@gmail.com', NULL, '2025-05-20', NULL, 'active', '2025-05-20 20:07:02', '2025-05-21 15:17:06'),
(4, NULL, 'Apartment in the center of Kiev 3 Bedrooms', 'Three-rooms apartment near the golden gate with good renovation. The apartment is ready to move in now. There is air conditioning and excellent noise insulation.', 'Stepan Rudansky Street, 4-6', NULL, 'Kiev', 'Kiev region', '02000', 50.46896253, 30.43113112, 'Apartment', 3, 2.0, 203, NULL, NULL, 'sale', 210000.00, 'USD', 1, 'Administrator', 'sftype1@gmail.com', NULL, '2025-05-20', NULL, 'active', '2025-05-20 20:09:57', '2025-05-21 15:17:06'),
(5, NULL, 'Rent House in Zhitomir', 'Rent a house in the suburbs of Zhitomir for private, 2-storey house with a swimming pool spacious kitchen and bathroom. Rent a week, if the daily rate is 200 dollars a day', 'Bohunska Street, 32', NULL, 'Zhitomir', 'Zhitomir', '10000', 50.28331181, 28.63185167, 'House', 4, 2.0, 100, 400, NULL, 'rent', 4000.00, 'USD', 1, 'Administrator', 'sftype1@gmail.com', NULL, '2025-05-20', NULL, 'active', '2025-05-20 21:06:42', '2025-05-21 20:53:17'),
(6, NULL, 'Rent apartment in Kiev', 'Rent studio apartment in the center of kiev on a monthly basis. Furniture presusvuyut, ban on animals.', 'Yurkivska Street, 28', NULL, 'Kiev', 'Kiev', '02000', 50.47281215, 30.50812125, 'Apartment', 1, 1.0, 54, NULL, NULL, 'rent', 800.00, 'USD', 1, 'Administrator', 'sftype1@gmail.com', NULL, '2025-05-20', NULL, 'pending', '2025-05-20 21:12:47', '2025-05-21 20:11:50'),
(7, NULL, 'Rent house in Dnipro', 'Rent one storey house for long term lease with excellent renovation and exterior decoration. At the conclusion of a contract for a year price 2 months discount.', 'Beethoven Street, 94', NULL, 'Dnipro', 'Dnepropetrovsk', '49000', 48.51836237, 35.03284425, NULL, 3, 2.0, 120, 243, NULL, 'rent', 2500.00, 'USD', 1, 'Administrator', 'sftype1@gmail.com', NULL, '2025-05-20', NULL, 'purchased', '2025-05-20 21:35:17', '2025-05-21 20:11:11'),
(8, 'AB1234567', 'Spacious Family Home in Green Valley', 'Beautiful 4-bedroom, 3-bathroom home perfect for a growing family. Features a large backyard, updated kitchen, and a two-car garage. Located in the desirable Green Valley school district. Close to parks and shopping.', 'Oak Street, 1223', NULL, 'Springfield', 'IL', '62704', 39.78048050, -89.63888347, NULL, 4, 3.0, 220, 500, NULL, 'sale', 350000.00, 'USD', 2, 'Alex', 'sftype2@gmail.com', NULL, '2025-05-20', NULL, 'purchased', '2025-05-20 21:49:33', '2025-05-21 21:40:13'),
(10, 'MLS Number 2', 'Title / Catchy Headline 1', 'Description 5', 'Address Line 1 (Street, Number) 6', 'Address Line 2 (Apt, Suite) 7', 'City 8', 'State/Region 9', 'ZIP Code 10', 50.45010000, 30.52340000, 'Property Type 11', 12, 13.0, 14, 15, '2016', 'sale', 4.30, 'USD', 1, 'Administrator', 'sftype1@gmail.com', '+13333334444', '2025-05-21', '2025-05-17', 'inactive', '2025-05-20 23:29:59', '2025-05-21 20:12:00'),
(13, 'MLS Number (optional): 2', 'Title / Catchy Headline: 2', 'Description:', 'Street, Number', 'Apt, Suite, etc.', 'City', 'State/Region', 'ZIP Code', 49.88755654, 24.01611328, NULL, 2, 1.0, 345, 456, '1999', 'sale', 234643.00, 'USD', 3, 'David', 'contact@gmail.com', '+38063033789', '2025-05-25', '2025-05-26', 'pending', '2025-05-25 14:52:23', '2025-05-25 22:38:01');

-- --------------------------------------------------------

--
-- Структура таблицы `property_photos`
--

CREATE TABLE `property_photos` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `property_photos`
--

INSERT INTO `property_photos` (`id`, `property_id`, `image_path`, `caption`, `is_primary`, `sort_order`) VALUES
(1, 2, 'images/properties/prop_2_682cd65d3d1376.89159575.png', NULL, 1, 0),
(2, 2, 'images/properties/prop_2_682cd65d3d3e78.32167008.png', NULL, 0, 1),
(3, 2, 'images/properties/prop_2_682cd65d3d5847.18421422.png', NULL, 0, 2),
(4, 2, 'images/properties/prop_2_682cd65d3e0245.02184702.png', NULL, 0, 3),
(5, 2, 'images/properties/prop_2_682cd65d3e1f40.08554736.png', NULL, 0, 4),
(6, 3, 'images/properties/prop_3_682ce0e63df004.85014971.png', NULL, 1, 0),
(7, 3, 'images/properties/prop_3_682ce0e63ea656.63535100.png', NULL, 0, 1),
(8, 3, 'images/properties/prop_3_682ce0e63ecf00.41541807.png', NULL, 0, 2),
(9, 3, 'images/properties/prop_3_682ce0e63f5c95.97237689.png', NULL, 0, 3),
(10, 3, 'images/properties/prop_3_682ce0e63f8067.63914219.png', NULL, 0, 4),
(11, 4, 'images/properties/prop_4_682ce1954d9c66.49873121.png', NULL, 1, 0),
(12, 4, 'images/properties/prop_4_682ce1954dc343.67282735.png', NULL, 0, 1),
(13, 4, 'images/properties/prop_4_682ce1954de4d3.24387879.png', NULL, 0, 2),
(14, 4, 'images/properties/prop_4_682ce1954e0af7.10385273.png', NULL, 0, 3),
(15, 4, 'images/properties/prop_4_682ce1954e9f25.14032102.png', NULL, 0, 4),
(16, 5, 'images/properties/prop_5_682ceee2036614.00196519.png', NULL, 1, 0),
(17, 5, 'images/properties/prop_5_682ceee20403a3.49968719.png', NULL, 0, 1),
(18, 5, 'images/properties/prop_5_682ceee2049233.02678946.png', NULL, 0, 2),
(19, 5, 'images/properties/prop_5_682ceee204bc33.84295768.png', NULL, 0, 3),
(20, 6, 'images/properties/prop_6_682cf04fe86725.84884056.png', NULL, 1, 0),
(21, 6, 'images/properties/prop_6_682cf04fe89715.77055461.png', NULL, 0, 1),
(22, 6, 'images/properties/prop_6_682cf04fe8bd11.99304476.png', NULL, 0, 2),
(23, 7, 'images/properties/prop_7_682cf595410635.91136145.png', NULL, 1, 0),
(24, 7, 'images/properties/prop_7_682cf5954139e3.75769914.png', NULL, 0, 1),
(25, 8, 'images/properties/prop_8_682cf8ed81aeb8.15797911.png', NULL, 1, 0),
(28, 10, 'images/properties/prop_10_682d1077d3efc3.54583987.png', NULL, 1, 0),
(29, 10, 'images/properties/prop_10_682d1077d48f32.72193406.png', NULL, 0, 1),
(30, 13, 'images/properties/prop_13_68332ea725ac81.18567683.png', NULL, 1, 0),
(31, 13, 'images/properties/prop_13_68332ea72668c6.51181728.png', NULL, 0, 1),
(32, 13, 'images/properties/prop_13_68332ea7269753.80344717.png', NULL, 0, 2),
(33, 13, 'images/properties/prop_13_68332ea726c744.29656747.png', NULL, 0, 3);

-- --------------------------------------------------------

--
-- Структура таблицы `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('home_improvement_news_id', '4'),
('securing_mortgage_news_id', '3');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `login` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `access_right` int(11) NOT NULL DEFAULT 1,
  `photo` mediumblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`, `name`, `email`, `pass`, `access_right`, `photo`) VALUES
(1, 'Admin', 'Administrator', 'sftype1@gmail.com', '$2y$10$3ccxOw/bO5ADnCNshQOz0ug.hzCfj/SRfB9hEQ81X2OQm7r8jISCa', 10, 0xffd8ffe000104a46494600010101006000600000ffdb0043000302020302020303030304030304050805050404050a070706080c0a0c0c0b0a0b0b0d0e12100d0e110e0b0b1016101113141515150c0f171816141812141514ffdb00430103040405040509050509140d0b0d1414141414141414141414141414141414141414141414141414141414141414141414141414141414141414141414141414ffc00011080100010003012200021101031101ffc4001f0000010501010101010100000000000000000102030405060708090a0bffc400b5100002010303020403050504040000017d01020300041105122131410613516107227114328191a1082342b1c11552d1f02433627282090a161718191a25262728292a3435363738393a434445464748494a535455565758595a636465666768696a737475767778797a838485868788898a92939495969798999aa2a3a4a5a6a7a8a9aab2b3b4b5b6b7b8b9bac2c3c4c5c6c7c8c9cad2d3d4d5d6d7d8d9dae1e2e3e4e5e6e7e8e9eaf1f2f3f4f5f6f7f8f9faffc4001f0100030101010101010101010000000000000102030405060708090a0bffc400b51100020102040403040705040400010277000102031104052131061241510761711322328108144291a1b1c109233352f0156272d10a162434e125f11718191a262728292a35363738393a434445464748494a535455565758595a636465666768696a737475767778797a82838485868788898a92939495969798999aa2a3a4a5a6a7a8a9aab2b3b4b5b6b7b8b9bac2c3c4c5c6c7c8c9cad2d3d4d5d6d7d8d9dae2e3e4e5e6e7e8e9eaf2f3f4f5f6f7f8f9faffda000c03010002110311003f00fa5d9cc8c598e49ef4da28a080a28a2800a28a2800a28a2800a2a586de49beeaf1ebdaaf43a7a260b9de7f4a00ce48da438552df415663d3646fbc420fccd692a855c0181e829682ac534d3635c6e6663f90a996ce15e918fc79a9a8a06355153eea85fa0c53a8a2800a6b22bfde50df514ea280216b485bac63f0e2a26d3636ced2cbfa8ab7450066c9a6c8bf7087fd0d5578da338652bf515b9484061823228158c2a2b4e5d3a37c95250fe95466b7920fbc38fef0e9412454514500145145001451450014e5731b0653823bd368a0028a28a0028a28a0028a2a582ddae1b0bc0ee7d2801888d230551926afdbe9e170d2fcc7fbbdaac416e96eb851cf73dcd4b41561071c0e052d145030a28ae73e207891fc2fe15bbbbb75692fa4c5bda44885d9e773b5005009382738033806a272508b93d91d386c3d4c556850a4af293497ccc6f0d7c53b5f117c45d73c328a8a965186b6981ff5e54ed987bed6c018feeb7a57795f3bf8e9acfc1b0f843c41a25bea86e7c3ecb05e4971a5dd40b342c4ef6679230a09667ea7acb5eadf113c7c9e15f87773e23b1db725e28dad0919563260231f6c1cfe15c14713653f6af58ebf27afe1b1f699ae46aa55c23cb69b51adee252d1f3c5a8dddf6e65cb3ff00b79f620f8cde22d63c27e02bfd5f46ba82da7b629bbcd83cc24348a9f2e4e011bb3c86aebf4c99ee34db49643ba492146638c649504d78d7c64f07cd63f07ef6fae35bd4eeefd1607baf32f1de0b82d2c608f2b3b15431c8da0741c9ad1f1b5e6ab61e24f8731596b3796906a127952dbc6c047f2c698380016ea4e189152ebca9d5939276b474bf76d1b4327a18ccba852a1523cdcf5ef2e56aea14e13b68aed6ee2df7e9b1ebd4578f5edaeade1ff8b5a5e8567e25d5a4b2d62c6579bed9289da265c9dd16e1b5090b8e98193c76ab9e139350d0be316afe19fed9d4351d31f4917c9fda13f9cf149e62afcac4703e63c74e9e95b2c4fbd671eb6f9ee7935320e5a4eac2ba7fbbf6a959abc79b95f4d1a77b6f7b743b7f0fd9f882df59d724d5efedaef4d9a656d361863daf04783b839c0c9e9dcf43eb81bd5e5df0d6f352d4bc53f1034ebad62fae12d2ee286de591d59a2521f3b415da3f05ed5ce7846c35bf167c39d62faf3c59acc32594f77e4b5acfe5bb32720bbf52381f28da3afaf111c45a29462ddf9baf6675e2323752b54956ad08287b24ed1693f6904e2d24bcb5daeeecf74a2b91f84bae5e7893e1ce87a8dfcbe75e4d0912487ab95765c9f7216baeaeea735520a6baea7c96330d3c1626a616a7c50938bb6d74ecc290f3c1e452d1567214ae34f0df347f29feef6aa0f1b46c55860d6e5453dba5c2e1baf623a8a05631a8a967b76b77c3723b1f5a8a82428a28a0028a28a0028a28a0028a2a5b781ae24da3a773e9400eb5b56b86f441d4d6ac71ac4a15460511c6b1a85518029d416145145001451450015c2eb9a1f8a753f1d695a8ac3a44ba1e9accd15bcb732acacecbb4ca711150ca0b6067b9e79e3baa2b3a94d5449367760f172c1ce5384536d38ebda4acedaad6d75730bc71a45d6bfe15d4b4cb482d6e26bb85a1db792347180c31b89556391d471d47515c1787fe15ebd75f0e2e7c17e27b8d3e4b254c5a5f58cb23cb13060ca195914100fbf4e3debd6a8acaa61e1527cf2ed6f91df84ceb1581c3fd5a8d9253534edaa947669df4fbb5eb73c8758f87fe3df117c3e9fc2ba86a5a1bc61628e3bb51379b2a248ac37f185385ea01ce00e324d5bf127817c5daa6ade11ba89f45b85d01bcc2d24b2c067621411b423ed036f5c9cf5c0e95ea74567f5483ddbe9d7b3ba3be3c4b8b835cb08249cdd946caf38a8c9e96de2addbb6cade6bad784fc57a87c49d1fc4d0dbe8cb069d03c02ddefa5dd20704139106011bb8ebd29cbe11f1441f16ae7c551c3a4358cb69fd9fe435e4a25f2b786127fa9c06f941dbd3b67bd7a45155f568def77bdfe673acf6ba8a87b38d95374f67f037cd6f8b7bdddf73ccfc1be13f177867c45e28d526b7d1671acca2e1618efa61e53ae70a498391f37271dba543e0df04f8b7c31e0ad6b449e3d1ae67bc79a48a64bc95554ca30c18792781c918ebd38eb5ea54528e1631b59bd2ff8ee5d4e20c456e7e7a70f7b92fa3ff976ad0fb5d169e7d4e37e14f86f58f077846d744d5c58b1b3cac53594cefe6296663b8322ed233d89cfb57654515d14e0a9c1416c8f13198a9e3b135315512529b6ddb6bbd5fdef50a28a2b438c28a28a006c91ac8a558641acabab56b76f543d0d6bd3648c488558641a00c3a2a5b881ade4da791d8fad4541014514500145145003a343230551926b5ede016f1ed1c9ea4d57d3edf6af98dd5ba7d2aed0520a28a2818514514005145140051451400514514005145140051451400514514005145140051451400514514005145140115c422e232a783d8fa5643a98d8ab0c115b954b50b7dcbe6a8e475fa50266751451412152db43e7cc17b753f4a8ab4f4f8b642588e58fe94016871c0e052d145058514514005145140051451400514514005145140051451400514aca518ab02ac0e08230452500145145001451450014514500145145001451450014879e0f22968a00c6b884c3332f6ea3e951569ea10ef8c381caff002acca081d1a1924551dce2b6d40500018038aced363dd317fee8fe7fe4d6950520a28a281851451400514514005145140051456af86bc3777e27d405ada8daa3992661f2c6bea7dfd077a00a167693dfdcc705bc4f3cf21c2c68324d7a27877e0fc92059b589bc91d7ecd0105bf16e83e833f5aee7c35e17b2f0bd9f9169196918e649df1bdcfbfb0ec3fae4d6ed0060e9de09d0f4b03c8d3202c0ee0f32f98c0fa82d9c7e15b6aab1a80aa147a018a7d1400c78d645daea197d1866b1b50f06e89aa2b09f4d83737578d76371eeb835b94500794ebff07e48c3cda44fe68ebf67b820377e0374f4e0e3eb5e797d6173a65d3dbdd40f6f32f549060fd7e9ef5f4cd61f893c2d65e28b316f7685645398e74c6f43edec7b8feb83401f3dd15abe25f0dddf8635036b743729e639947cb22fa8f7f51dab2a800a28a2800a28a2800a28a2800a28a280119432904641e0d62488639194f638adcaccd4a3db306ecc3fcff4a04cb1a6aed84b63ef1ab750da2edb78c7b67f3e6a6a06145145001451450014514500145145004fa7d8cda95e456b026f966708abee7fa57bef85fc356fe17d312d20cbcac77cd29eaef8e4fb0f41fd726b85f83fa00926b8d6264c88ff00730647723e66fcb033eed5ead4005145140051451400514514005145140185e28f0d5bf8a34c7b49f292a9df0cabd51f1d7dc7a8feb835e0579693585d4b6d3c6629a2628e87b115f4d5794fc60f0f08e68358857893f733e3fbc07cadf9023f01401e6b4514500145145001451450014514500154f524dd0ab63953572a1bc5dd6d20f6cfe5cd00488bb11547618a751450014514500145145001451450014514aaa5d82a82cc4e000324d007d07e0dd3c697e17d3600bb5bc90ec339f99be63fa9adca647188d4228c2a8c0a7d00145145001451450014514500145145001583e36d346a9e15d4a1c3161099155464964f9801f5c63f1adea63a0914ab0cab0c1a00f98a8a28a0028a28a0028a28a0028a28a0029aebbd197d4629d4500145351b7a2b7a8cd3a800a28a2800a28a2800a28a2800a28a2803e9d4712286539561914fac1f04ea4354f0ae9b3658b08446ccc724b27ca49fae33f8d6f5001451450014514500145145001451450014c92411a17638551934fac4f19ea234bf0bea5396dade494538cfccdf28fd4d007cf6cc5d8b312cc4e493c9349451400514514005145140051451400514527b9f4a008acdb75b467db1f954d54f4d7dd0b2e7953572800a28a2800a28a2800a28a2800a28a2803d2be0ff008884734fa3ccd8127efa0cff00780f997f200fe06bd5abe65b3bb9b4fba8ae6090c5346c1d1c7622bdf7c2fe25b7f1469897707c9229d9344dd51f1d3dc7a1feb91401bb451450014514500145145001451450015e53f183c40249adf4785f223fdf4f8f523e55fcb271eeb5dcf8a3c4b6fe16d31eee7cbcac764310eaef8e07b0f53fd702bc0f50be9b52bc9aea77df34ce5d9bdcff004a00828a28a0028a28a0028a28a0028a28a0029b236c466fee8ddf953aa0be7f2edd8e7af14014f4d936cc57b30ff3fd6b4eb0e3731c8ac3b1cd6dab06504720f22812168a28a0614514500145145001451450015ade1af125df867501756a7729e2485beec8be87dfd0f6ac9a2803e83f0df8a6cbc51666e2d1cac8a71240f8de87dc7a1ec7fae456ed7ccd63a85ce99749716b33dbccbd1e3383f4fa7b57a1e83f18248c243ac41e68e9f68b700376e4af4f5e463e9401ead4561e9fe32d135455306a506e6e89236c6e3d9b06b66391645dc8c197d54e6801f45319d6352ccc147a938ac4d47c6da1e960f9fa9c0581da5216f3181f421738fc68037ab0bc4be29b2f0bd9fda2edcb48c711c098dee7dbd8773fd702b86f117c609240d0e8f0f923a7da6700b7e0bd07d4e7e95e7779793dfdcc93dc4af3cf21cb48e724d00687897c4977e26d40dd5d1daa388e15fbb1afa0f7f53deb268a2800a28a2800a28a2800a28a2800a28a2800aa3a9c98d89ff02ff3fad5e271ec2b1aea432cee7b74140991edad2d3e5dd1ec2795e9f4acfa92094c32ab76eff4a0935e8a4073c8e452d058514514005145140051451400514514005145140052ab1460ca4ab039047045251400f56411b02a0b7d0ff3cf1f91cfb5328a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a43ebd0500437927976ed8382781595b6ac5e4e249b03eeaf1505040514514017ec6e372f96c791d3e9572b1558c6c194e08ad682613c61875ee2829125145140c28a28a0028ad5d0fc2fa978864d9636cd22038695be545fab1fe5d6bd2741f8476366ab26a72b5ecdd4c684ac63dbd4fe9f4a00f29b1d36ef529bcab4b696e64eeb121623dce3a575ba4fc27d62fb0d7462b18fbf98db9ff21fd48af63b3b382c20586da18ede25e91c6a140fc0558a00f3cb1f835a6c383777b7172e3fe79811a9fc393fad6edb7c3bf0f5a90574d473ff004d5d9ff42715d35140142df41d36d5710585ac3ff5ce151fd2adc70a47f75157fdd18a928a008a482397efc6affef283556e343d3ae8626d3ed661ff004d2156fe62afd1401cddd7c3cf0fdd83bb4d8d49ef1b327f235837df06f4d9b26d2f6e2d9bd2402451fc8feb5e85450078aea9f09359b152f6ad0dfaff007636d8ff0091e3f5ae46fb4fbad366f2aeada5b793fbb2a153fad7d3155eeeca0d42dda0b9852785baa48a083401f33d15ebdae7c23d3ef37cba6cad6529e91b1dd17f88fccfd2bcdb5df0bea5e1d936df5b34684e1655f991be87fa75a00caa28a2800a28a2800aad7971e4c785fbedc0a9e490451966e9d85654d319a42cdf8502647451450485145140054904cd03ee1d3b8f5a8e8a00d98e4122865e41a75655bdd1b76f553d4569c722c8bb94e4505962ceca7d42ea3b7b789a79a4385451926bd43c2bf09e2b7d973ac91349d45aa1f957fde3dcfb0e3eb4bf0a753d105b1b58a316daab0f9da5604cdfee9f4ff67f9f5af49a00861863b78d628a358a351854500003d00153514500145145001451450014514500145145001451450014514500150cd0c7711b452c6b2c6c30c8c01047a106a6a2803cd7c55f09a2b8dd73a330864ea6d5cfcadfee9edf43c7d2bcbef2c6e34fb87b7b985e1990e19241822be9aaf35f8ada9e8ad6e2d258c5c6aaa3e468880611fed1f43fddfe5d6803ca6918ed04938039a1982a962702b3aeaefcf6dabc20fd680197570676ff0063b0feb50d1450405145140051451400514514005490ccd0b6e5fc47ad4745006c5addee6578d8a48a72307041f515e9fe13f8afe5ac76bad0665036ade20c9ff818eff51f977af195628c0a9c1abd05f86f964e0ff7a82ae7d4b6b770de5ba4f6f2a4d0b8cac91b02a7f1a9ebe72d0fc51a8f87a4f32c2e9a3463968cfcd1b7d474fc7ad7a6f87fe2d585f2ac5a929b09f1feb172d137f51f8fe740cf40a2a1b7b88aea15961912689865648d8329fa1153500145145001451450014514500145145001451505c5cc5670b4b3ca90c4bd5e460aa3f134013d4175770d9dbbcf712a430a0cb492300a3f1ae1bc41f16ac2c55a2d354dfcf8ff0058d95897fa9fc3f3af33d7bc51a8f88a5f32fee4ba29cac43e58d3e83fa9e6803b8f177c56f32392d3450ca0fcad78c307df60eddb93efc77af30babbdaccf2317918e4e4e493ea6aadc5f81958f93fdeaa4cc5d8963926815c926b879bef1e3b2d4545141214514500145145001452b298d8ab0c114940051451400514514005145140124533c27e538f6ed5722d411b01c6d3ebdab3e8a00e9f49d72f7479bcfd3eee4b763d7cb3c37d4743f8d769a67c62d42dd425f5a457b8180e8de5313ea7823f202bc955d90e54953ed5623d4245fbd87a0ab9ef9a7fc59d0ef389da7b26c64f9919619f40573fa815bf69e2ad1af150c5aa5a316e8a6650df9139af9ad7514fe2561f4e6a55bc81bf8ff003e2819f52060c010720d3abe6086f0c67314c54ffb0f8ab90ebfa95bff00a9d46ea3ff0072761fc8d007d274d2c14124e00af9be6f106a531ccba95d487fdb9d8ff5aa335d9931e6cdbbd37366803e8abbf1468f64ae66d4ed10af55f394b7e40e6b02fbe2ce856bc44d71787fe98c581ff8f62bc39af215fe3cfd0544da8a7f0ab1faf1401e97aa7c61d4ae72b636b0d92e3ef487cc7fa8e83f435c5eadae5eeb1279da85e497041c8f31be55cfa0e83f0ac192fe46fbb841ed55d9d9ce58963ef40ae5f975055c841b8fa9e954a499e63966cfb76a651412145145001451450014514500145147dec00324f1401fffd9),
(2, 'Alex', 'Alex', 'sftype2@gmail.com', '$2y$10$nBFkQEGz0bnM2c1zAodtt.Pva3GgRT2j/IUqIJ1DE7dKEP8lECikG', 1, NULL),
(3, 'David', 'David', 'realestate18052025@gmail.com', '$2y$10$pJ4IOnc41Sv7BJQVqLgRv.LQGFX./RcjFCYYVPiNzbT0mvNU1g7dG', 1, 0xffd8ffe000104a46494600010101006000600000ffdb0043000302020302020303030304030304050805050404050a070706080c0a0c0c0b0a0b0b0d0e12100d0e110e0b0b1016101113141515150c0f171816141812141514ffdb00430103040405040509050509140d0b0d1414141414141414141414141414141414141414141414141414141414141414141414141414141414141414141414141414ffc00011080100010003012200021101031101ffc4001f0000010501010101010100000000000000000102030405060708090a0bffc400b5100002010303020403050504040000017d01020300041105122131410613516107227114328191a1082342b1c11552d1f02433627282090a161718191a25262728292a3435363738393a434445464748494a535455565758595a636465666768696a737475767778797a838485868788898a92939495969798999aa2a3a4a5a6a7a8a9aab2b3b4b5b6b7b8b9bac2c3c4c5c6c7c8c9cad2d3d4d5d6d7d8d9dae1e2e3e4e5e6e7e8e9eaf1f2f3f4f5f6f7f8f9faffc4001f0100030101010101010101010000000000000102030405060708090a0bffc400b51100020102040403040705040400010277000102031104052131061241510761711322328108144291a1b1c109233352f0156272d10a162434e125f11718191a262728292a35363738393a434445464748494a535455565758595a636465666768696a737475767778797a82838485868788898a92939495969798999aa2a3a4a5a6a7a8a9aab2b3b4b5b6b7b8b9bac2c3c4c5c6c7c8c9cad2d3d4d5d6d7d8d9dae2e3e4e5e6e7e8e9eaf2f3f4f5f6f7f8f9faffda000c03010002110311003f00fa5d9cc8c598e49ef4da28a080a28a2800a28a2800a28a2800a2a586de49beeaf1ebdaaf43a7a260b9de7f4a0667246d21c2a96fa0ab31e9b237de2107e66b49542ae00c0f414b40ec534d3635c6e6663f90a996ce15e918fc79a9a8a06355153eea85fa0c53a8a2800a6b22bfde50df514ea280216b485bac63f0e2a26d3636ced2cbfa8ab7450066c9a6c8bf7087fd0d5578da338652bf515b9484061823228158c2a2b4e5d3a37c95250fe95466b7920fbc38fef0e94088a8a28a04145145001451450014e5731b0653823bd368a0028a28a0028a28a0028a2a582ddae1b0bc0ee7d2801888d230551926afdbe9e170d2fcc7fbbdaac416e96eb851cf73dcd4b41561071c0e052d145030a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a43cf07914b450052b8d3c37cd1fca7fbbdaa83c6d1b1561835b9514f6e970b86ebd88ea28158c6a2a59eddaddf0dc8ec7d6a2a090a28a2800a28a2800a28a2800a28a96de06b89368e9dcfa5003ad6d5ae1bd107535ab1c6b128551814471ac6a154600a750505145140c28a28a0028a28a0028a28a0028a28a0028a28a0028a28a0028a28a0028a28a0028a28a006c91ac8a558641acabab56b76f543d0d6bd3648c488558641a0461d152dc40d6f26d3c8ec7d6a2a090a28a2800a28a2801d1a19182a8c935af6f00b78f68e4f526abe9f6fb57cc6eadd3e957682905145140c28a28a0028a28a0028a28a0028a28a0028a28a0028a28a0028a28a0028a28a0028a28a0028a28a0028a28a008ae211711953c1ec7d2b21d4c6c558608adcaa5a85bee5f354723afd281333a8a28a090a96da1f3e60bdba9fa5455a7a7c5b212c472c7f4a065a1c703814b4514141451450014514500145145001451450014514500145145001452b29462ac0ab038208c11494005145140051451400514514005145140051451400521e783c8a5a28031ae2130cccbdba8fa5455a7a843be30e072bfcab3282474686491547738adb5014000600e2b3b4d8f74c5ff00ba3f9ff935a540d05145140c28a28a0028a28a0028a28a0028a2b5bc33e1abbf146a02d6d46d51cc9330f9635f53efe83bd0067d9d9cfa85d476f6d13cf3c870b1a0c935e8be1df83d248166d627f2475fb340416fc5ba0fa0cfd6bbcf0cf856c7c2b67e45a21691b99277c6f73efec3b0feb935b340187a7782342d2c0f234c80b03b83ccbe6303ea0b671f856d2aac6a155428f40314ea2801af1ac8bb5d432fa30cd63ea1e0bd13545613e9b06e6eaf1aec6e3dd706b6a8a00f2bf107c1d9230f368f71e68ebf66b820377e0374f4e0e3eb5e757d6173a65d3dbdd40f6f32f549060fd7e9ef5f4cd63789bc2b63e2ab3105da9591798e74c6f43edec7b8feb83401f3c515ade26f0d5df85f5036b743729e639947cb22fa8f7f51dab26800a28a2800a28a2800a28a2800a28a280119432904641e0d62488639194f638adcaccd4a3db306ecc3fcff004a04cb1a6aed84b63ef1ab750da2edb78c7b67f3e6a6a06145145001451450014514500145145004fa7d8cda9dec3696e9be699c22afb9fe95f407857c336fe15d2d6d203be463be698f577c727d87a0feb935c27c1df0f8926b8d6264c88ff730647723e66fcb033eed5ea9400514514005145140051451400514514018de2af0cdbf8ab4b7b49fe4914ef8661d51f1d7dc7a8feb835f3f5e59cda7ddcb6d71198a7898a3a1ec457d355e55f187c3a239a0d6215e24fdccf8fef01f2b7e408fc050079a51451400514514005145140051451400553d493742ad8e54d5ca86f1775b483db3f973401222ec4551d8629d45140051451400514514005145140051452aa9760aa0b3138000c93401f42f82f4f1a5f85f4d802ed6f243b0ce7e66f98fea6b6a9b1a08d151461546053a800a28a2800a28a2800a28a2800a28a2800ac3f1be9c354f0a6a70618b084c8a1464964f9801f5c63f1adca6ba8915958655860d007cc3451450014514500145145001451450014d75de8cbea314ea2800a29a8dbd15bd4669d40051451400514514005145140051451401f4f230915594e5586453ab0fc11a88d53c29a64f962c2111b16392593e524fd719fc6b72800a28a2800a28a2800a28a2800a28a2800a6c8e234676e15464d3ab17c69a88d2fc2fa94e5b6b792514e33f337ca3f53401f3d331762cc4b313924f24d251450014514500145145001451450014514d76d88cc7b0cd004766dbada33ed8fcaa6aa7a6bee85973ca9ab940051451400514514005145140051451401e97f07bc44239a7d1e66c093f7d067fbc07ccbf9007f035eab5f32d9de4da7ddc5736f218a78983a38ec457d03e15f135bf8ab4b4bb806c914ec9a13d51f1d3dc7a1feb91401b3451450014514500145145001451450015e57f18bc40249adf4785f223fdf4f8f523e55fcb271eeb5ddf8abc4d6fe15d2daee71be463b21887577c703d87a9feb815f3fea17d36a77b35d5c3ef9a672ecdee7fa500414514500145145001451450014514500150ddb6db790fb63f3e2a6aa9a93ed842e7ef1a00afa6c9b662bd987f9feb5a75871b98e4561d8e6b6d5832823907914090b45145030a28a2800a28a2800a28a2800ad6f0cf89aefc2fa80bab53b94f1242df7645f43efe87b564d1401f43f867c5563e2ab333da31591789207c6f43ee3d0f63fd722b66be66b1d42e74cba4b8b599ede65e8f19c1fa7d3dabd17c3ff18a48c243ac5bf9a3a7da6dc00ddb92bd3d7918fa5007aa5158ba7f8d344d5154c1a941b9ba248db1b8f66c1ad8491645dc8c197d54e6801d45359d6352ccc147a938ac5d47c6fa16960f9fa9c0581da5216f3181f421738fc680372b1bc4de2ab1f0ad9f9f76c5a46e238131bdcfb7b0ee7fae05707e22f8c32481a1d1e0f2474fb4ce016fc17a0fa9cfd2bceaf2f27d42ea4b8b995e79e439691ce49a00d0f13789aefc51a81baba3b54711c2bf7635f41efea7bd64d1450014514500145145001451450014514500159ba949ba609fdd1fcffc8ad1660a093c01589239924663dce6813136d6969f2ee8f613caf4fa567d4904a61955bb77fa50235e8a4073c8e452d050514514005145140051451400514514005145140052ab1460ca4ab039047045251400f56411b02a0b7d0ff3cf1f91cfb5328a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a4271c9e050056d424db08507058fe9599b6a6b89bce94b76e82a3a090a28a28117ec6e372f96c791d3e9572b1558c6c194e08ad682613c61875ee2829125145140c28a28a0028ad5d0fc2fa9788a5d9636cd22038695be545fab1fe5d6bd2b41f8436366a926a72b5ecdd4c684ac63dbd4fe9f4a00f28b1d36ef539bcab4b696e64eeb121623dce3a575da4fc25d66fb0d7462b08fbf98db9ff21fd48af64b4b3834f8161b6863b7857a471a8503f015350079ed8fc19d361c1bbbdb8b961ff3cc08d4fe1c9fd6b7ad7e1df876d482ba6a39ff00a6aecffa138ae928a00a36fa169b6831069f6b0ffd738547f4ab690c71fdc455ff0074629f4500472411cbf7e357ff007941aab71a1e9d7431369f6b30ff00a690ab7f3157a8a00e72ebe1e787af33bb4c8d09ef133263f235817df06b4d9b26d2f6e2d9bd2402451fc8feb5e85450078b6a9f08f59b152f6ad0dfaff7636d8ff91e3f5ae42fb4ebad366f2aeeda5b693fbb2a153fad7d3150de59c1a85bb41730a4f0b754914106803e65a2bd835df843a7de6f974d95ac653d2363ba2ff11f99fa579a6bbe17d4bc3926dbeb668d09c2cabf3237d0ff004eb40195451450014514500154efa7dabe5af56ebf4ab13cc208f71e4f61594ee6462cdc934098da28a282428a28a002a4826681f70e9dc7ad4745006cc72091432f20d3ab2adee4dbb7aa9ea2b4e391645dca722828b165633ea3751db5b44d3cf21c2a20c935ea3e15f84b15becb9d6889a4ea2d50fcabfef1ee7d871f5a5f84faa688b6e6d628c5b6acc3e769581337fba7d3fd9fe7d6bd22818c8618ede258a28d628d4615100000f40053e8a2800a28a2800a28a2800a28a2800a28a2800a28a2800a28a2800a64d0c77113452c6b2c6c30c8e01047a1069f450079b78abe12c571bee745610c9d4dab9f95bfdd3dbe878fa57975e58dc69d72f6f730bc1321c324830457d355e6ff0016354d11adc5a4b18b8d5947c8d1100c23fda3e87fbbfcbad00793d35dc46a598e00a2491635dcc702b32e2e4dc37a28e82810d9e669df27a761e951d1450485145140051451400514514005490ccd0b6e5fc47ad4745006c5addee6578d8a48a72307041f515ea1e11f8b1e524769ad8665036ade20c9ff00818eff0051f977af185628c0a9c1abd05f86f964e0ff007a82ae7d4f6b750dedba4f6f2a4d0b8cac91b02a7f1a96be72d0bc51a8f87a4f32c2e9a3463968cfcd1b7d474fc7ad7a7f87be2e69f7cab16a686c27ff009e8b9689bfa8fc7f3a0677d45476f7115d4292c1224d130cac91b0653f422a4a0028a28a0028a28a0028a28a0028a28a0028a2a2b8b986ce169679521897abc8c1547e268025a8aeaea1b2b779ee254861419692460147e35c37887e2e69f62ad169886fe7ff009e8d95897fa9fc3f3af31d7bc51a8f88a5f32fee4ba29cac43e58d3e83fa9e6803b9f177c58f36392d3450ca0fcad78c307df60eddb93efc77af2fbabbdaccf2317918e4e4e493ea6aadc5f81958f93fdeaa4cc5d8963926815c7cd334cd963f41e951d1450485145140051451400514aca6362ac304525001451450014514500145145004914cf09f94e3dbb55c8b5046c071b4faf6acfa2819d3e93ae5ee8f379fa7ddc96ec7af96786fa8e87f1aed74bf8c9a85b284beb48af70301d1bca627d4f047e4057922bb21ca92a7daac47a848bf7b0f40ee7bf69ff16b42bce2769ec9b193e6c65867d015cfea056fdaf8ab46bd5568754b46ddd14cca1bf22735f34aea287ef2b0fa7352ade42dfc7f9f140cfa9410c010720d2d7cc10de18ce6298a9ff61f15721d7f52b7ff0053a8dd47fee4ec3f91a00fa4e9090a09270057cdd378835298e65d4aea43fedcec7fad519aecc98f366dde9b9b3401f465d78a347b10e66d4ed10af55f394b7e40e6b9fbef8b5a0daf1135c5e1ff00a631607fe3d8af0d6bc857f8f3f415136a283eeab1faf1401e97aafc62d4ae72b636b0d92e3ef39f31fea3a0fd0d717ab6bb7bac4be76a17925c10723cc6f9573e83a0fc2b064bf91beee107b5576767396258fbd02b97e5d41572106e3ea7a552926798e59b3edda9945020a28a2810514514005145140051452aa99182a8c93401ffd9);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mls_number` (`mls_number`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_state` (`state`),
  ADD KEY `idx_zip_code` (`zip_code`),
  ADD KEY `idx_listing_type` (`listing_type`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_bedrooms` (`bedrooms`),
  ADD KEY `idx_bathrooms` (`bathrooms`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Индексы таблицы `property_photos`
--
ALTER TABLE `property_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_property_id` (`property_id`);

--
-- Индексы таблицы `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login_UNIQUE` (`login`),
  ADD UNIQUE KEY `email_UNIQUE` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT для таблицы `property_photos`
--
ALTER TABLE `property_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `property_photos`
--
ALTER TABLE `property_photos`
  ADD CONSTRAINT `property_photos_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

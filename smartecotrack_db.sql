-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 18, 2026
-- Server version: 10.4.27-MariaDB
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smartecotrack_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT 'default.jpg',
  `role` enum('user','admin') DEFAULT 'user',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `quiz_score` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `category` enum('flooding','landslide','deforestation','pollution','wildfire','wildlife','other') NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','resolved','closed') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disaster_tips`
--

CREATE TABLE `disaster_tips` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `icon` varchar(50) DEFAULT 'leaf',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disaster_tips`
--

INSERT INTO `disaster_tips` (`id`, `category`, `title`, `content`, `icon`) VALUES
(1, 'Flood', 'Before a Flood', 'Prepare an emergency kit with food, water, and first aid supplies. Know your evacuation routes and keep important documents in waterproof containers.', 'water'),
(2, 'Flood', 'During a Flood', 'Move to higher ground immediately. Avoid walking or driving through flood waters. Just 6 inches of moving water can knock you down.', 'shield'),
(3, 'Landslide', 'Warning Signs', 'Watch for tilting trees, new cracks in pavement, and sudden changes in water flow. Listen for unusual sounds like trees cracking or boulders knocking.', 'mountain'),
(4, 'Earthquake', 'Drop, Cover, Hold', 'Drop to your hands and knees. Cover your head and neck under a sturdy table. Hold on until the shaking stops.', 'activity'),
(5, 'Fire', 'Fire Prevention', 'Create a defensible space around your home. Clear dry vegetation within 30 feet. Have an evacuation plan ready.', 'flame'),
(6, 'Typhoon', 'Typhoon Preparedness', 'Secure loose objects, trim trees, reinforce windows. Stock up on food, water, batteries, and medicine for at least 3 days.', 'wind');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` char(1) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `points` int(11) DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `category`, `points`) VALUES
(1, 'What is the first thing you should do during an earthquake?', 'Run outside immediately', 'Drop, cover, and hold on', 'Stand in a doorway', 'Call emergency services', 'B', 'Earthquake', 10),
(2, 'How many inches of moving water can knock down an adult?', '2 inches', '6 inches', '12 inches', '24 inches', 'B', 'Flood', 10),
(3, 'What is the emergency hotline number in the Philippines?', '117', '911', '8888', 'All of the above', 'D', 'General', 10),
(4, 'Which of these is NOT a sign of an impending landslide?', 'Tilting trees', 'Cracks in pavement', 'Clear blue sky', 'Unusual water flow', 'C', 'Landslide', 10),
(5, 'What should your emergency kit contain?', 'Just food', 'Food, water, first aid, flashlight', 'Only water', 'Phone charger only', 'B', 'General', 10),
(6, 'How far should defensible space around your home extend?', '10 feet', '30 feet', '50 feet', '100 feet', 'B', 'Fire', 10),
(7, 'During a typhoon, what wind speed is considered Signal No. 3?', '30-60 kph', '61-120 kph', '121-170 kph', '171-220 kph', 'C', 'Typhoon', 10),
(8, 'What does DENR stand for?', 'Department of Energy and Natural Resources', 'Department of Environment and Natural Resources', 'Division of Environmental and Natural Research', 'Department of Ecological and Natural Resources', 'B', 'General', 10);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_results`
--

CREATE TABLE `quiz_results` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_contacts`
--

CREATE TABLE `emergency_contacts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `type` varchar(100) NOT NULL,
  `region` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emergency_contacts`
--

INSERT INTO `emergency_contacts` (`id`, `name`, `phone`, `type`, `region`) VALUES
(1, 'National Emergency Hotline', '911', 'General Emergency', 'Nationwide'),
(2, 'NDRRMC', '(02) 8911-5061', 'Disaster Response', 'Nationwide'),
(3, 'Philippine Red Cross', '143', 'Medical Emergency', 'Nationwide'),
(4, 'DENR Hotline', '(02) 8926-3011', 'Environmental', 'Nationwide'),
(5, 'PHIVOLCS', '(02) 8426-1468', 'Volcano/Earthquake', 'Nationwide'),
(6, 'PAGASA', '(02) 8284-0800', 'Weather', 'Nationwide'),
(7, 'BFP (Fire)', '(02) 8426-0219', 'Fire Emergency', 'Nationwide'),
(8, 'Philippine Coast Guard', '(02) 8527-8481', 'Maritime Emergency', 'Nationwide');

-- --------------------------------------------------------

--
-- Table structure for table `evacuation_routes`
--

CREATE TABLE `evacuation_routes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `type` varchar(100) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `facilities` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evacuation_routes`
--

INSERT INTO `evacuation_routes` (`id`, `name`, `description`, `latitude`, `longitude`, `type`, `capacity`, `facilities`) VALUES
(1, 'Rizal Park Evacuation Center', 'Main evacuation center in Manila', 14.58250000, 120.97860000, 'Evacuation Center', 5000, 'Water, Medical, Food'),
(2, 'Quezon City Hall Complex', 'Designated evacuation area', 14.64450000, 121.04880000, 'Government Building', 3000, 'Medical, Power, Water');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `response` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `disaster_tips`
--
ALTER TABLE `disaster_tips`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `evacuation_routes`
--
ALTER TABLE `evacuation_routes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `disaster_tips`
--
ALTER TABLE `disaster_tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `quiz_results`
--
ALTER TABLE `quiz_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `evacuation_routes`
--
ALTER TABLE `evacuation_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD CONSTRAINT `quiz_results_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
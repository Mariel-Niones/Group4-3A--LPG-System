-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 28, 2026 at 04:06 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bgs_lpg`
--

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` varchar(20) NOT NULL,
  `branch_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `branch_name`) VALUES
('b1', 'Tagbilaran - Main Branch'),
('b10', 'Loay Branch'),
('b11', 'Valencia Branch'),
('b12', 'Jagna Branch'),
('b15', '235'),
('b2', 'Inabanga Branch'),
('b3', 'Ubay Branch'),
('b4', 'Carmen Branch'),
('b5', 'Jetafe Branch'),
('b6', 'Talibon Branch'),
('b7', 'Cortes Branch'),
('b8', 'Dao Branch'),
('b9', 'Tubigon Branch');

-- --------------------------------------------------------

--
-- Table structure for table `branch_inventory`
--

CREATE TABLE `branch_inventory` (
  `id` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `min_stock` int(11) NOT NULL DEFAULT 5,
  `branch_id` int(11) DEFAULT NULL,
  `product_id` varchar(10) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branch_inventory`
--

INSERT INTO `branch_inventory` (`id`, `name`, `category`, `price`, `min_stock`, `branch_id`, `product_id`, `quantity`) VALUES
('p1', 'LPG 2.7 kg Cylinder (Refill)', 'Cylinder', 280.00, 8, NULL, NULL, 0),
('p2', 'LPG 5 kg Cylinder (Refill)', 'Cylinder', 520.00, 8, NULL, NULL, 0),
('p3', 'LPG 11 kg Cylinder (Refill)', 'Cylinder', 960.00, 10, NULL, NULL, 0),
('p4', 'LPG 22 kg Cylinder (Refill)', 'Cylinder', 1900.00, 6, NULL, NULL, 0),
('p5', 'LPG 50 kg Cylinder (Refill)', 'Cylinder', 4300.00, 4, NULL, NULL, 0),
('p6', 'LPG Regulator', 'Accessory', 350.00, 6, NULL, NULL, 0),
('p7', 'LPG Hose (1.5 m)', 'Accessory', 180.00, 8, NULL, NULL, 0),
('p8', 'Hose Clamp', 'Accessory', 35.00, 15, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `branch_sales`
--

CREATE TABLE `branch_sales` (
  `id` int(11) NOT NULL,
  `branch_id` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `payment_method` varchar(30) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `category`, `price`) VALUES
(1, '2.7kg LPG Cylinder', 'Cylinder', 300.00),
(3, 'LPG Hose Clamp', 'Accessory', 25.00),
(6, '11kg LPG Cylinder', 'Cylinder', 900.00),
(7, '5kg LPG Cylinder', 'Cylinder', 450.00),
(8, 'Gas Regulator', 'Accessory', 250.00),
(9, 'Gas Stove', 'Accessory', 350.00),
(10, 'Fuel Canister', 'Fuel Canister', 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` varchar(30) NOT NULL,
  `sale_date` date NOT NULL,
  `ts` bigint(20) NOT NULL,
  `branch_id` varchar(20) NOT NULL,
  `user` varchar(50) DEFAULT NULL,
  `cust` varchar(100) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `pay` varchar(20) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` varchar(30) NOT NULL,
  `pid` varchar(20) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock`
--

CREATE TABLE `stock` (
  `branch_id` varchar(20) NOT NULL,
  `product_id` varchar(20) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock`
--

INSERT INTO `stock` (`branch_id`, `product_id`, `qty`) VALUES
('b1', 'p1', 20),
('b1', 'p2', 20),
('b1', 'p3', 20),
('b1', 'p4', 20),
('b1', 'p5', 20),
('b1', 'p6', 20),
('b1', 'p7', 20),
('b1', 'p8', 20),
('b2', 'p1', 20),
('b2', 'p2', 20),
('b2', 'p3', 20),
('b2', 'p4', 20),
('b2', 'p5', 20),
('b2', 'p6', 20),
('b2', 'p7', 20),
('b2', 'p8', 20),
('b3', 'p1', 20),
('b3', 'p2', 20),
('b3', 'p3', 20),
('b3', 'p4', 20),
('b3', 'p5', 20),
('b3', 'p6', 20),
('b3', 'p7', 20),
('b3', 'p8', 20);

-- --------------------------------------------------------

--
-- Table structure for table `stockins`
--

CREATE TABLE `stockins` (
  `id` varchar(20) NOT NULL,
  `stockin_date` date NOT NULL,
  `branch_id` varchar(20) NOT NULL,
  `pid` varchar(20) NOT NULL,
  `qty` int(11) NOT NULL,
  `ref` varchar(60) DEFAULT NULL,
  `user` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_in`
--

CREATE TABLE `stock_in` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `delivery_reference` varchar(100) DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `staff_name` varchar(100) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','staff') NOT NULL DEFAULT 'staff',
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `staff_name`, `branch_name`, `password`, `role`, `active`) VALUES
(1, 'Store Owner', 'Tagbilaran - Main Branch', '$2y$10$lPYScvviYhJ2bt651r/PVOIHIT8j3srEZ7YqNZM7HNRTAGLQhktS2', 'owner', 1),
(2, 'Mariel Niones', 'Inabanga Branch', '$2y$10$4h.kwOYxtJDCHGG/ErFkr.DsVt6/0AkA/GXBJ7wn7v6HsZYspNkKG', 'staff', 0),
(3, 'Jessie Cenabre', 'Tagbilaran - Main Branch', '$2y$10$VRSOklEHucVFpszQv29hbu0WzB6pLK0w.n3MDtXooYrD6B5U31/Na', 'staff', 0),
(4, 'hdfgrurhgh', 'Ubay Branch', '$2y$10$hWvfF8rm.aSFWtBAZXkUDuvfrTYf8.ENXD.2po.xo7ofh73RPp/ny', 'staff', 0),
(5, 'hhh11', 'Tubigon Branch', '$2y$10$SCavOzbP/1svRCJMCXqMseVTWvqA3vkhDt0Z6.nszTXirxr3VF1R2', 'staff', 0),
(6, 'jgjjh', 'Jetafe Branch', '$2y$10$QABlnBYvSddzOjJDSOWs5OMBXfyWyQQEKjPwBgXwbBed8cP7RnPBO', 'staff', 0),
(7, 'kjgjkh', 'Ubay Branch', '$2y$10$FCKyAuUmSggVw6CndZq18uCVOpOegg/DA36s0tR4UuXCrkyEbAqAy', 'staff', 0),
(8, 'ako12', 'Dao Branch', '$2y$10$EfJc8CW5rPAscjKNDfuVJeekpkiatIYyR.O3wbNDC0O0l1bKmtb1W', 'staff', 0),
(9, '123455557', 'Tubigon Branch', '$2y$10$iOuRJPIwnpegpQYNbWAZ1e5btp0MLF7GqID51yftEjMtZNBIe9w3O', 'staff', 0),
(10, 'hfkjhdg', 'Carmen Branch', '$2y$10$GIY2V83XYT0kY1HTytVyj.9sCM0ooDg2niUbEQga0gR7lF1zCdD5y', 'staff', 0),
(11, 'hgrtj12', 'Loay Branch', '$2y$10$QcbA5cLHY2/J8I61HD0tMel2pKPyfDfp4sbLUVw0LM4Y9RX0NKnkS', 'staff', 0),
(12, 'mmmmmm3', 'Inabanga Branch', '$2y$10$fia/Q3RElHxDz1ONiTB/f.kFwJz33Pw0WLS1oUS.xXQNOdjr9zQ42', 'staff', 0),
(13, '444444689', 'Cortes Branch', '$2y$10$tIUM6IJuHh4B3eDmE/79P.R3i7RI3gGBuefxxN9dAsS5cLt2erkg6', 'staff', 0),
(14, 'ddddddd50', 'Dao Branch', '$2y$10$iaZQSkorGBD9H4vLrN.7UuS4OvlRcL1rQbSibcXwFgD5oWHfa7Gpi', 'staff', 0),
(15, 'Mariel Mendoza', 'Inabanga Branch', '$2y$10$xfWhdLHWNUj9hajc87exoOdwMMniIOOYQDG41bqf5iJG4cx.Xk4Ny', 'staff', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branch_inventory`
--
ALTER TABLE `branch_inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branch_sales`
--
ALTER TABLE `branch_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_date` (`sale_date`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`branch_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stockins`
--
ALTER TABLE `stockins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `branch_sales`
--
ALTER TABLE `branch_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_in`
--
ALTER TABLE `stock_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `branch_inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stockins`
--
ALTER TABLE `stockins`
  ADD CONSTRAINT `stockins_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

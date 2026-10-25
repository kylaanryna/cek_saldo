-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 25, 2026 at 07:46 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `peminjaman`
--

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL,
  `waktu` datetime NOT NULL,
  `pengguna` varchar(100) NOT NULL,
  `aksi` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `waktu`, `pengguna`, `aksi`) VALUES
(1, '2026-10-14 14:36:16', 'Administrator', 'Pinjam Laptop oleh yumna (S001)'),
(2, '2026-10-14 14:40:30', 'Administrator', 'Pinjam HP oleh Husna aulia (S002)'),
(3, '2026-10-14 14:48:12', 'Administrator', 'Pinjam Laptop, Terminal oleh Husna aulia (S002)'),
(4, '2026-10-14 14:48:18', 'Administrator', 'Kembali semua (HP, Laptop, Terminal) oleh Husna aulia'),
(5, '2026-10-14 16:05:00', 'Administrator', 'Login'),
(6, '2026-10-14 16:08:16', 'Administrator', 'Logout'),
(7, '2026-10-14 16:09:21', 'Administrator', 'Login'),
(8, '2026-10-14 16:10:27', 'Administrator', 'Ubah nama sekolah'),
(9, '2026-10-14 16:28:54', 'Administrator', 'Pinjam HP, Laptop, Terminal oleh Ayla Sarah (S007)'),
(10, '2026-10-14 16:32:20', 'Administrator', 'Kembali semua (HP, Laptop, Terminal) oleh Ayla Sarah'),
(11, '2026-10-14 16:33:52', 'Administrator', 'Logout'),
(12, '2026-10-14 16:36:05', 'Administrator', 'Login'),
(13, '2026-10-14 16:39:45', 'Administrator', 'Logout'),
(14, '2026-10-14 16:42:35', 'Administrator', 'Login'),
(15, '2026-10-14 16:42:51', 'Administrator', 'Pinjam HP, Laptop, Terminal oleh Ayla Sarah (S007)'),
(16, '2026-10-14 16:43:05', 'Administrator', 'Kembali semua (HP, Laptop, Terminal) oleh Ayla Sarah'),
(17, '2026-10-14 16:43:46', 'Administrator', 'Pinjam HP oleh Ayla Sarah (S007)'),
(18, '2026-10-14 16:44:12', 'Administrator', 'Kembali HP oleh Ayla Sarah'),
(19, '2026-10-14 21:29:25', 'Administrator', 'Login'),
(20, '2026-10-14 21:45:39', 'Administrator', 'Tambah akun penjaga (petugas)'),
(21, '2026-10-14 21:45:53', 'Administrator', 'Logout'),
(22, '2026-10-14 21:46:05', 'kayla', 'Login'),
(23, '2026-10-14 21:49:03', 'kayla', 'Pinjam HP, Terminal oleh yumna (S001)'),
(24, '2026-10-14 21:49:14', 'kayla', 'Kembali semua (Laptop, HP, Terminal) oleh yumna'),
(25, '2026-10-14 21:49:34', 'kayla', 'Pinjam HP oleh yumna (S001)'),
(26, '2026-10-14 21:51:03', 'kayla', 'Logout'),
(27, '2026-10-14 21:51:08', 'Administrator', 'Login'),
(28, '2026-10-14 21:52:02', 'Administrator', 'Pinjam Laptop, Terminal oleh yumna (S001)'),
(29, '2026-10-14 21:52:10', 'Administrator', 'Kembali semua (HP, Laptop, Terminal) oleh yumna'),
(30, '2026-10-14 21:52:16', 'Administrator', 'Pinjam HP oleh yumna (S001)'),
(31, '2026-10-14 21:52:20', 'Administrator', 'Kembali HP oleh yumna'),
(32, '2026-10-14 21:53:17', 'Administrator', 'Logout'),
(33, '2026-10-14 21:53:25', 'kayla', 'Login'),
(34, '2026-10-14 21:53:54', 'kayla', 'Logout'),
(35, '2026-10-14 21:53:58', 'Administrator', 'Login'),
(36, '2026-10-15 17:31:04', 'Administrator', 'Login'),
(37, '2026-10-15 17:31:37', 'Administrator', 'Logout'),
(38, '2026-10-15 17:31:44', 'kayla', 'Login'),
(39, '2026-10-15 17:31:52', 'kayla', 'Logout'),
(40, '2026-10-15 17:31:57', 'Administrator', 'Login'),
(41, '2026-10-15 17:51:59', 'Administrator', 'Logout'),
(42, '2026-10-15 17:52:06', 'kayla', 'Login'),
(43, '2026-10-15 17:52:11', 'kayla', 'Logout'),
(44, '2026-10-15 17:52:15', 'Administrator', 'Login'),
(45, '2026-10-16 07:52:36', 'Administrator', 'Login'),
(46, '2026-10-17 10:06:34', 'Administrator', 'Login'),
(47, '2026-10-17 10:13:39', 'Administrator', 'Pinjam HP oleh yumna (S001)'),
(48, '2026-10-17 10:13:50', 'Administrator', 'Kembali HP oleh yumna'),
(49, '2026-10-17 10:15:12', 'Administrator', 'Pinjam HP oleh yumna (S001)'),
(50, '2026-10-17 10:15:16', 'Administrator', 'Kembali HP oleh yumna'),
(51, '2026-10-17 10:15:27', 'Administrator', 'Pinjam Laptop oleh yumna (S001)'),
(52, '2026-10-17 10:15:46', 'Administrator', 'Kembali Laptop oleh yumna'),
(53, '2026-10-17 10:15:54', 'Administrator', 'Pinjam HP, Laptop oleh yumna (S001)'),
(54, '2026-10-17 10:16:05', 'Administrator', 'Kembali semua (HP, Laptop) oleh yumna'),
(55, '2026-10-17 10:17:01', 'Administrator', 'Pinjam Laptop oleh yumna (S001)'),
(56, '2026-10-17 10:17:08', 'Administrator', 'Kembali Laptop oleh yumna'),
(57, '2026-10-17 10:47:06', 'Administrator', 'Pinjam HP oleh yumna (S001)'),
(58, '2026-10-17 10:47:12', 'Administrator', 'Kembali HP oleh yumna'),
(59, '2026-10-17 13:49:38', 'Administrator', 'Login'),
(60, '2026-10-17 13:53:16', 'Administrator', 'Logout'),
(61, '2026-10-17 13:53:22', 'kayla', 'Login'),
(62, '2026-10-17 14:01:57', 'kayla', 'Logout'),
(63, '2026-10-17 14:02:02', 'Administrator', 'Login'),
(64, '2026-10-18 10:11:44', 'Administrator', 'Login'),
(65, '2026-10-18 10:16:01', 'Administrator', 'Logout'),
(66, '2026-10-18 10:16:11', 'kayla', 'Login'),
(67, '2026-10-18 10:16:25', 'kayla', 'Pinjam HP oleh Husna aulia (S002)'),
(68, '2026-10-18 10:16:29', 'kayla', 'Kembali HP oleh Husna aulia'),
(69, '2026-10-18 10:16:41', 'kayla', 'Pinjam HP, Laptop, Terminal oleh Husna aulia (S002)'),
(70, '2026-10-18 10:17:10', 'kayla', 'Kembali semua (HP, Laptop, Terminal) oleh Husna aulia'),
(71, '2026-10-18 10:20:41', 'kayla', 'Logout'),
(72, '2026-10-18 10:20:55', 'Administrator', 'Login'),
(73, '2026-10-18 10:33:55', 'Administrator', 'Hapus santri Hafidzah (S003)'),
(74, '2026-10-18 10:34:20', 'Administrator', 'Pinjam HP oleh yumna (S001)'),
(75, '2026-10-18 10:35:04', 'Administrator', 'Kembali HP oleh yumna'),
(76, '2026-10-18 10:35:59', 'Administrator', 'Logout'),
(77, '2026-10-18 10:36:05', 'Administrator', 'Login'),
(78, '2026-10-18 10:36:37', 'Administrator', 'Pinjam HP oleh yumna (S001)'),
(79, '2026-10-18 10:36:49', 'Administrator', 'Pinjam Laptop oleh yumna (S001)'),
(80, '2026-10-18 10:37:01', 'Administrator', 'Kembali semua (HP, Laptop) oleh yumna'),
(81, '2026-10-18 10:44:31', 'Administrator', 'Logout'),
(82, '2026-10-18 10:44:37', 'kayla', 'Login'),
(83, '2026-10-18 10:44:49', 'kayla', 'Pinjam HP oleh yumna (S001)'),
(84, '2026-10-18 10:49:18', 'kayla', 'Logout'),
(85, '2026-10-18 10:49:22', 'Administrator', 'Login'),
(86, '2026-10-18 11:06:53', 'Administrator', 'Logout'),
(87, '2026-10-18 11:06:57', 'Administrator', 'Login'),
(88, '2026-10-18 11:08:20', 'Administrator', 'Ubah data santri Ayla Sarah (S007, FQ)'),
(89, '2026-10-18 11:09:31', 'Administrator', 'Hapus santri Raihanah (S006)'),
(90, '2026-10-18 11:10:20', 'Administrator', 'Ubah data santri Ayla Sarah (S007, FQ)'),
(91, '2026-10-18 11:12:39', 'Administrator', 'Hapus semua data santri dan riwayat peminjaman'),
(92, '2026-10-18 11:13:11', 'Administrator', 'Import santri dari CSV: 5 berhasil, 0 dilewati'),
(93, '2026-10-18 11:13:17', 'Administrator', 'Hapus semua data santri dan riwayat peminjaman'),
(94, '2026-10-18 13:31:28', 'Administrator', 'Login'),
(95, '2026-10-18 13:32:01', 'Administrator', 'Import santri dari CSV: 5 berhasil, 0 dilewati'),
(96, '2026-10-18 13:32:07', 'Administrator', 'Hapus santri ayla sarah (3)'),
(97, '2026-10-18 13:32:37', 'Administrator', 'Ubah data santri husna aulia (2, QBS)'),
(98, '2026-10-18 13:57:52', 'Administrator', 'Hapus semua data santri dan riwayat peminjaman'),
(99, '2026-10-18 14:17:51', 'Administrator', 'Logout'),
(100, '2026-10-18 14:17:55', 'kayla', 'Login'),
(101, '2026-10-18 14:18:23', 'kayla', 'Logout'),
(102, '2026-10-18 14:18:27', 'Administrator', 'Login'),
(103, '2026-10-18 14:18:54', 'Administrator', 'Hapus santri Yumna (S001)'),
(104, '2026-10-18 14:19:35', 'Administrator', 'Tambah santri Yumna (S001, FQ)'),
(105, '2026-10-18 14:19:45', 'Administrator', 'Ubah data santri Yumna (S001, FQ)'),
(106, '2026-10-18 14:24:04', 'Administrator', 'Login'),
(107, '2026-10-18 14:52:24', 'Administrator', 'Pinjam HP oleh Yumna (S001)'),
(108, '2026-10-18 16:31:37', 'Administrator', 'Login'),
(109, '2026-10-18 16:34:57', 'Administrator', 'Pinjam Laptop oleh Yumna (S001)'),
(110, '2026-10-18 16:36:44', 'Administrator', 'Import santri dari CSV: 5 berhasil, 0 dilewati'),
(111, '2026-10-18 16:37:52', 'Administrator', 'Tambah santri Hafidzah (S009)'),
(112, '2026-10-18 16:38:07', 'Administrator', 'Ubah data santri Hafidzah (6)'),
(113, '2026-10-18 16:38:12', 'Administrator', 'Ubah data santri Yumna (7)'),
(114, '2026-10-18 16:38:37', 'Administrator', 'Kembali Laptop oleh Yumna'),
(115, '2026-10-18 16:39:34', 'Administrator', 'Pinjam Laptop oleh Yumna (7)'),
(116, '2026-10-18 16:39:44', 'Administrator', 'Kembali semua (HP, Laptop) oleh Yumna'),
(117, '2026-10-18 16:42:08', 'Administrator', 'Pinjam HP oleh Yumna (7)'),
(118, '2026-10-18 16:42:10', 'Administrator', 'Kembali HP oleh Yumna'),
(119, '2026-10-18 17:03:46', 'Administrator', 'Ubah data santri ayla sarah (3)'),
(120, '2026-10-18 17:04:12', 'Administrator', 'Ubah data santri Hafidzah (6)'),
(121, '2026-10-18 17:04:24', 'Administrator', 'Ubah data santri husna aulia (2)'),
(122, '2026-10-18 17:04:32', 'Administrator', 'Ubah data santri kinzi (4)'),
(123, '2026-10-18 17:24:20', 'Administrator', 'Ubah data santri raihanah (5)'),
(124, '2026-10-18 17:24:32', 'Administrator', 'Ubah data santri raihanah (5)'),
(125, '2026-10-18 17:24:45', 'Administrator', 'Ubah data santri Yumna (7)'),
(126, '2026-10-18 17:24:55', 'Administrator', 'Ubah data santri yumna (1)'),
(127, '2026-10-18 17:35:26', 'Administrator', 'Login'),
(128, '2026-10-18 17:36:48', 'Administrator', 'Ubah data santri yumna (1)'),
(129, '2026-10-18 17:38:44', 'Administrator', 'Ubah data santri yumna (1)'),
(130, '2026-10-18 21:31:44', 'Administrator', 'Login'),
(131, '2026-10-18 21:35:39', 'Administrator', 'Login'),
(132, '2026-10-18 21:36:11', 'Administrator', 'Login'),
(133, '2026-10-18 21:36:25', 'Administrator', 'Login'),
(134, '2026-10-19 20:17:55', 'Administrator', 'Login'),
(135, '2026-10-20 20:41:13', 'Administrator', 'Login'),
(136, '2026-10-21 10:11:17', 'kayla', 'Login'),
(137, '2026-10-21 10:15:17', 'kayla', 'Logout'),
(138, '2026-10-21 10:15:23', 'Administrator', 'Login'),
(139, '2026-10-21 10:35:42', 'Administrator', 'Ubah data santri ayla sarah (3)'),
(140, '2026-10-21 10:41:27', 'Administrator', 'Tambah santri Nasywa (8)'),
(141, '2026-10-21 10:41:53', 'Administrator', 'Hapus santri Nasywa (8)'),
(142, '2026-10-21 10:42:15', 'Administrator', 'Tambah santri Nasywa (8)'),
(143, '2026-10-21 10:48:30', 'Administrator', 'Ubah data santri kinzi (4)'),
(144, '2026-10-21 10:48:51', 'Administrator', 'Hapus santri yumna (1)'),
(145, '2026-10-21 13:50:20', 'Administrator', 'Login'),
(146, '2026-10-21 14:02:24', 'Administrator', 'Logout'),
(147, '2026-10-21 14:09:57', 'Administrator', 'Login'),
(148, '2026-10-22 16:08:07', 'Administrator', 'Login'),
(149, '2026-10-22 16:45:21', 'Administrator', 'Logout'),
(150, '2026-10-22 16:56:01', 'Administrator', 'Login'),
(151, '2026-10-24 10:16:44', 'Administrator', 'Login'),
(152, '2026-10-24 10:46:32', 'Administrator', 'Logout'),
(153, '2026-10-24 10:46:38', 'kayla', 'Login'),
(154, '2026-10-24 13:37:52', 'Administrator', 'Login'),
(155, '2026-10-24 13:39:30', 'Administrator', 'Logout'),
(156, '2026-10-24 13:50:39', 'Administrator', 'Login'),
(157, '2026-10-24 13:52:57', 'Administrator', 'Logout'),
(158, '2026-10-24 13:53:41', 'Administrator', 'Login'),
(159, '2026-10-24 13:55:52', 'Administrator', 'Logout'),
(160, '2026-10-24 13:56:23', 'Administrator', 'Login'),
(161, '2026-10-24 14:02:10', 'Administrator', 'Logout'),
(162, '2026-10-24 14:02:19', 'kayla', 'Login'),
(163, '2026-10-24 16:37:58', 'Administrator', 'Login');

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id` int(11) NOT NULL,
  `santri_id` int(11) NOT NULL,
  `jenis` varchar(20) NOT NULL,
  `waktu_pinjam` datetime NOT NULL,
  `jatuh_tempo` datetime NOT NULL,
  `waktu_kembali` datetime DEFAULT NULL,
  `kondisi_kembali` varchar(10) DEFAULT NULL,
  `catatan` varchar(300) NOT NULL DEFAULT '',
  `petugas` varchar(100) NOT NULL DEFAULT '',
  `ruang` varchar(10) NOT NULL DEFAULT 'QBS'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `santri_id`, `jenis`, `waktu_pinjam`, `jatuh_tempo`, `waktu_kembali`, `kondisi_kembali`, `catatan`, `petugas`, `ruang`) VALUES
(33, 23, 'HP', '2026-10-18 14:52:24', '2026-10-18 15:05:00', '2026-10-18 16:39:44', 'Baik', '', 'Administrator', 'QBS'),
(34, 23, 'Laptop', '2026-10-18 16:34:57', '2026-10-18 17:05:00', '2026-10-18 16:38:37', 'Baik', '', 'Administrator', 'QBS'),
(35, 23, 'Laptop', '2026-10-18 16:39:34', '2026-10-18 17:05:00', '2026-10-18 16:39:44', 'Baik', '', 'Administrator', 'QBS'),
(36, 23, 'HP', '2026-10-18 16:42:08', '2026-10-18 17:05:00', '2026-10-18 16:42:10', 'Baik', '', 'Administrator', 'QBS'),
(37, 26, 'HP', '2026-10-18 21:42:30', '2026-10-19 09:35:00', '2026-10-18 21:42:58', 'Baik', '', 'Administrator', 'FQ'),
(38, 26, 'HP', '2026-10-19 20:18:26', '2026-10-19 21:30:00', '2026-10-19 20:18:35', 'Baik', '', 'Administrator', 'FQ'),
(39, 26, 'HP', '2026-10-19 20:18:54', '2026-10-19 21:30:00', '2026-10-19 20:19:03', 'Baik', '', 'Administrator', 'FQ'),
(40, 29, 'HP', '2026-10-20 20:44:28', '2026-10-20 21:30:00', '2026-10-20 20:48:14', 'Baik', '', 'Administrator', 'FQ'),
(41, 29, 'HP', '2026-10-20 20:48:36', '2026-10-20 21:30:00', '2026-10-20 20:48:47', 'Baik', '', 'Administrator', 'FQ'),
(42, 26, 'Laptop', '2026-10-20 20:55:41', '2026-10-20 21:30:00', '2026-10-21 10:35:48', 'Baik', '', 'Administrator', 'FQ'),
(43, 26, 'Terminal', '2026-10-20 20:56:01', '2026-10-20 21:30:00', '2026-10-21 10:35:48', 'Baik', '', 'Administrator', 'FQ'),
(44, 23, 'HP', '2026-10-21 10:15:08', '2026-10-21 11:05:00', '2026-10-21 10:16:24', 'Baik', '', 'kayla', 'FQ'),
(45, 23, 'Laptop', '2026-10-21 10:16:12', '2026-10-21 11:05:00', '2026-10-21 10:16:24', 'Baik', '', 'Administrator', 'FQ'),
(46, 23, 'HP', '2026-10-21 10:31:46', '2026-10-21 11:05:00', '2026-10-21 10:40:19', 'Baik', '', 'Administrator', 'FQ'),
(47, 26, 'HP', '2026-10-21 10:39:58', '2026-10-21 11:05:00', '2026-10-21 10:43:32', 'Baik', '', 'Administrator', 'FQ'),
(48, 31, 'HP', '2026-10-21 10:42:21', '2026-10-21 11:05:00', '2026-10-21 10:43:29', 'Baik', '', 'Administrator', 'QBS'),
(49, 26, 'HP', '2026-10-21 10:53:45', '2026-10-21 11:05:00', '2026-10-21 13:57:52', 'Baik', 'lupa balikin', 'Administrator', 'FQ'),
(50, 31, 'HP', '2026-10-21 11:09:05', '2026-10-21 15:05:00', NULL, NULL, '', 'Administrator', 'QBS'),
(51, 26, 'HP', '2026-10-24 14:02:45', '2026-10-24 15:05:00', '2026-10-24 14:02:51', 'Baik', '', 'kayla', 'FQ'),
(52, 26, 'HP', '2026-10-24 14:03:00', '2026-10-24 15:05:00', '2026-10-24 14:03:26', 'Baik', '', 'kayla', 'FQ'),
(53, 26, 'Laptop', '2026-10-24 14:03:00', '2026-10-24 15:05:00', '2026-10-24 14:03:26', 'Baik', '', 'kayla', 'FQ'),
(54, 26, 'HP', '2026-10-24 16:40:02', '2026-10-24 17:05:00', '2026-10-24 16:40:09', 'Baik', '', 'Administrator', 'FQ');

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan`
--

CREATE TABLE `pengaturan` (
  `k` varchar(100) NOT NULL,
  `v` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengaturan`
--

INSERT INTO `pengaturan` (`k`, `v`) VALUES
('batas_HP', '1'),
('batas_Laptop', '1'),
('batas_Terminal', '1'),
('durasi_HP', '3'),
('durasi_Laptop', '5'),
('durasi_Terminal', '3'),
('nama_sekolah', 'Sekolah Impian');

-- --------------------------------------------------------

--
-- Table structure for table `saldo_akun`
--

CREATE TABLE `saldo_akun` (
  `santri_id` int(11) NOT NULL,
  `saldo` bigint(20) NOT NULL DEFAULT 0,
  `pin_hash` varchar(255) DEFAULT NULL,
  `pin_len` tinyint(4) DEFAULT NULL,
  `pin_gagal` int(11) NOT NULL DEFAULT 0,
  `terkunci_sampai` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saldo_akun`
--

INSERT INTO `saldo_akun` (`santri_id`, `saldo`, `pin_hash`, `pin_len`, `pin_gagal`, `terkunci_sampai`, `updated_at`) VALUES
(1, 900000, '$2y$10$RpoGxtiyQzkGHGSLqinjAO0hR6FLYdSz7ba0o7Mqn2kzKF1A1JIP2', NULL, 0, NULL, '2026-10-17 11:11:00'),
(11, 110000, NULL, NULL, 0, NULL, '2026-10-17 11:06:28'),
(23, 115000, '$2y$10$8vIWaFgitEcILIY2MK9hQ.1XHarAdUc2wc4zWkDlHNnKNkjUQuYLy', 6, 0, NULL, '2026-10-24 16:10:32'),
(25, 100000, NULL, NULL, 0, NULL, '2026-10-21 16:30:40'),
(26, 50000, '$2y$10$t0xTGdSHoafKWb897Ee3duq0tFDewhirk0kEpc6z/8/mnEZVnCk5G', 6, 0, NULL, '2026-10-24 16:38:30'),
(27, 100000, NULL, NULL, 0, NULL, '2026-10-21 16:30:45'),
(28, 100000, NULL, NULL, 0, NULL, '2026-10-21 16:31:04'),
(29, 100000, NULL, NULL, 0, NULL, '2026-10-21 16:30:34'),
(31, 100000, NULL, NULL, 0, NULL, '2026-10-21 16:30:51');

-- --------------------------------------------------------

--
-- Table structure for table `saldo_log`
--

CREATE TABLE `saldo_log` (
  `id` int(11) NOT NULL,
  `waktu` datetime NOT NULL,
  `pengguna` varchar(100) NOT NULL,
  `aksi` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saldo_log`
--

INSERT INTO `saldo_log` (`id`, `waktu`, `pengguna`, `aksi`) VALUES
(1, '2026-10-17 11:03:27', 'Administrator', 'Login'),
(2, '2026-10-17 11:04:18', 'Administrator', 'Tambah saldo Rp 100.000 untuk Ayla Sarah (S007)'),
(3, '2026-10-17 11:05:11', 'Administrator', 'Ubah pengaturan keamanan PIN'),
(4, '2026-10-17 11:05:39', 'Administrator', 'Hapus PIN Ayla Sarah (S007)'),
(5, '2026-10-17 11:06:28', 'Administrator', 'Tambah saldo Rp 10.000 untuk Ayla Sarah (S007)'),
(6, '2026-10-17 11:06:43', 'Administrator', 'Tambah saldo Rp 10.000 untuk yumna (S001)'),
(7, '2026-10-17 11:09:22', 'Administrator', 'PIN dibuat di kiosk oleh yumna (S001)'),
(8, '2026-10-17 11:09:22', 'Administrator', 'Tarik Rp 5.000 oleh yumna (S001)'),
(9, '2026-10-17 11:09:36', 'Administrator', 'Tarik Rp 5.000 oleh yumna (S001)'),
(10, '2026-10-17 11:10:43', 'Administrator', 'Tambah saldo Rp 1.000.000 untuk yumna (S001)'),
(11, '2026-10-17 11:11:00', 'Administrator', 'Tarik Rp 100.000 oleh yumna (S001)'),
(12, '2026-10-17 13:49:21', 'Administrator', 'Login'),
(13, '2026-10-17 14:00:12', 'Administrator', 'Logout'),
(14, '2026-10-17 14:00:18', 'Administrator', 'Login'),
(15, '2026-10-17 14:52:28', 'Administrator', 'Logout'),
(16, '2026-10-17 14:52:48', 'Administrator', 'Login'),
(17, '2026-10-18 10:11:31', 'Administrator', 'Login'),
(18, '2026-10-18 14:21:15', 'Administrator', 'Login'),
(19, '2026-10-21 13:42:32', 'Administrator', 'Login'),
(20, '2026-10-21 14:02:10', 'Administrator', 'Logout'),
(21, '2026-10-21 14:02:29', 'Administrator', 'Login'),
(22, '2026-10-21 14:03:33', 'Administrator', 'Tambah saldo Rp 100.000 untuk ayla sarah (3)'),
(23, '2026-10-21 14:06:25', 'Administrator', 'PIN dibuat di kiosk oleh ayla sarah (3)'),
(24, '2026-10-21 14:06:25', 'Administrator', 'Tarik Rp 10.000 oleh ayla sarah (3)'),
(25, '2026-10-21 14:06:37', 'Administrator', 'Tarik Rp 10.000 oleh ayla sarah (3)'),
(26, '2026-10-21 14:07:49', 'Administrator', 'Tarik Rp 10.000 oleh ayla sarah (3)'),
(27, '2026-10-21 14:13:03', 'Administrator', 'Logout'),
(28, '2026-10-21 14:13:13', 'Administrator', 'Login'),
(29, '2026-10-21 14:48:34', 'Administrator', 'Logout'),
(30, '2026-10-21 14:48:38', 'kayla', 'Login'),
(31, '2026-10-21 14:49:41', 'kayla', 'Tarik Rp 5.000 oleh ayla sarah (3)'),
(32, '2026-10-21 14:52:00', 'kayla', 'Tarik Rp 5.000 oleh ayla sarah (3)'),
(33, '2026-10-21 14:52:25', 'kayla', 'Logout'),
(34, '2026-10-21 14:52:31', 'Administrator', 'Login'),
(35, '2026-10-21 16:30:08', 'Administrator', 'Login'),
(36, '2026-10-21 16:30:34', 'Administrator', 'Tambah saldo Rp 100.000 untuk Hafidzah (6)'),
(37, '2026-10-21 16:30:40', 'Administrator', 'Tambah saldo Rp 100.000 untuk husna aulia (2)'),
(38, '2026-10-21 16:30:45', 'Administrator', 'Tambah saldo Rp 100.000 untuk kinzi (4)'),
(39, '2026-10-21 16:30:51', 'Administrator', 'Tambah saldo Rp 100.000 untuk Nasywa (8)'),
(40, '2026-10-21 16:31:04', 'Administrator', 'Tambah saldo Rp 100.000 untuk raihanah (5)'),
(41, '2026-10-21 16:31:10', 'Administrator', 'Tambah saldo Rp 100.000 untuk Yumna (7)'),
(42, '2026-10-21 16:48:12', 'Administrator', 'PIN dibuat di kiosk oleh Yumna (7)'),
(43, '2026-10-21 16:48:12', 'Administrator', 'Tarik Rp 20.000 oleh Yumna (7)'),
(44, '2026-10-21 16:48:22', 'Administrator', 'Tarik Rp 10.000 oleh Yumna (7)'),
(45, '2026-10-21 16:49:54', 'Administrator', 'Logout'),
(46, '2026-10-21 16:49:58', 'kayla', 'Login'),
(47, '2026-10-21 16:55:35', 'kayla', 'Tarik Rp 5.000 oleh Yumna (7)'),
(48, '2026-10-21 16:55:49', 'kayla', 'Logout'),
(49, '2026-10-21 16:56:01', 'Administrator', 'Login'),
(50, '2026-10-22 10:19:48', 'Administrator', 'Login'),
(51, '2026-10-22 16:05:35', 'Administrator', 'Login'),
(52, '2026-10-22 16:09:25', 'Administrator', 'Tambah akun wali santi (sri.santi03)'),
(53, '2026-10-22 16:09:59', 'Administrator', 'Tautkan 1 santri ke wali sri.santi03'),
(54, '2026-10-22 16:24:42', 'Administrator', 'Ubah akun wali sri.santi03'),
(55, '2026-10-22 16:24:49', 'Administrator', 'Reset password wali sri.santi03'),
(56, '2026-10-22 16:30:46', 'Administrator', 'Login wali'),
(57, '2026-10-22 16:38:08', 'Administrator', 'Tambah akun wali testing (testing), tautkan 1 santri'),
(58, '2026-10-22 16:38:26', 'Administrator', 'Logout wali'),
(59, '2026-10-22 16:38:38', 'Administrator', 'Reset password wali testing'),
(60, '2026-10-22 16:39:46', 'Administrator', 'Reset password wali testing'),
(61, '2026-10-22 16:39:56', 'Administrator', 'Login wali'),
(62, '2026-10-22 16:56:15', 'Administrator', 'Logout wali'),
(63, '2026-10-22 16:57:02', 'Administrator', 'Login wali'),
(64, '2026-10-24 10:15:01', 'Administrator', 'Login'),
(65, '2026-10-24 10:15:42', 'Administrator', 'Logout'),
(66, '2026-10-24 10:18:15', 'Wali: santi', 'Login wali'),
(67, '2026-10-24 10:19:58', 'Administrator', 'Login'),
(68, '2026-10-24 10:21:12', 'Administrator', 'Ubah pengaturan top up'),
(69, '2026-10-24 10:21:55', 'Administrator', 'Buat top up Rp 10.000 untuk ayla sarah (TU2610241021543571CF)'),
(70, '2026-10-24 10:28:56', 'Tripay', 'Top up berhasil Rp 10.000 untuk ayla sarah (3) [manual oleh Administrator]'),
(71, '2026-10-24 10:31:33', 'Administrator', 'Tautkan 2 santri ke wali sri.santi03'),
(72, '2026-10-24 14:06:15', 'Administrator', 'Login'),
(73, '2026-10-24 14:06:55', 'Administrator', 'Logout'),
(74, '2026-10-24 14:07:45', 'Administrator', 'Login'),
(75, '2026-10-24 14:11:40', 'Administrator', 'Logout'),
(76, '2026-10-24 14:11:46', 'kayla', 'Login'),
(77, '2026-10-24 14:12:44', 'kayla', 'Tarik Rp 10.000 oleh ayla sarah (3)'),
(78, '2026-10-24 14:13:15', 'kayla', 'Logout'),
(79, '2026-10-24 14:13:50', 'Wali: santi', 'Login wali'),
(80, '2026-10-24 14:14:45', 'Wali: santi', 'Buat top up Rp 50.000 untuk Yumna (TU261024141445A9B088)'),
(81, '2026-10-24 14:15:37', 'Wali: santi', 'Logout wali'),
(82, '2026-10-24 16:06:24', 'Administrator', 'Login'),
(83, '2026-10-24 16:10:32', 'Tripay', 'Top up berhasil Rp 50.000 untuk Yumna (7) [manual oleh Administrator]'),
(84, '2026-10-24 16:10:52', 'Administrator', 'Logout'),
(85, '2026-10-24 16:11:07', 'Wali: santi', 'Login wali'),
(86, '2026-10-24 16:25:03', 'Administrator', 'Login'),
(87, '2026-10-24 16:25:07', 'Administrator', 'Logout'),
(88, '2026-10-24 16:25:13', 'Administrator', 'Login'),
(89, '2026-10-24 16:25:18', 'Administrator', 'Logout'),
(90, '2026-10-24 16:25:30', 'Administrator', 'Login'),
(91, '2026-10-24 16:26:38', 'Administrator', 'Login wali'),
(92, '2026-10-24 16:26:55', 'Administrator', 'Logout'),
(93, '2026-10-24 16:27:01', 'Administrator', 'Login'),
(94, '2026-10-24 16:33:35', 'Administrator', 'Login'),
(95, '2026-10-24 16:38:30', 'Administrator', 'Tarik Rp 10.000 oleh ayla sarah (3)'),
(96, '2026-10-24 16:40:35', 'Administrator', 'Logout wali');

-- --------------------------------------------------------

--
-- Table structure for table `saldo_pengaturan`
--

CREATE TABLE `saldo_pengaturan` (
  `k` varchar(100) NOT NULL,
  `v` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saldo_pengaturan`
--

INSERT INTO `saldo_pengaturan` (`k`, `v`) VALUES
('kelipatan', '1000'),
('maks_harian', '1000000'),
('maks_tarik', '500000'),
('min_tarik', '5000'),
('pin_buat_kiosk', '1'),
('pin_kunci_menit', '5'),
('pin_maks_gagal', '3'),
('reset_detik', '30'),
('topup_aktif', '1'),
('topup_maks', '1000000'),
('topup_min', '10000'),
('topup_pending_maks', '3');

-- --------------------------------------------------------

--
-- Table structure for table `saldo_remember`
--

CREATE TABLE `saldo_remember` (
  `id` int(11) NOT NULL,
  `tipe` varchar(10) NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` varchar(24) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `rotated_at` datetime NOT NULL,
  `last_used` datetime DEFAULT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `ua` varchar(200) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saldo_topup`
--

CREATE TABLE `saldo_topup` (
  `id` int(11) NOT NULL,
  `merchant_ref` varchar(50) NOT NULL,
  `wali_id` int(11) NOT NULL,
  `santri_id` int(11) NOT NULL,
  `nominal` bigint(20) NOT NULL,
  `metode` varchar(30) NOT NULL DEFAULT '',
  `metode_nama` varchar(80) NOT NULL DEFAULT '',
  `reference` varchar(80) DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'NEW',
  `total_bayar` bigint(20) DEFAULT NULL,
  `biaya` bigint(20) DEFAULT NULL,
  `pay_code` varchar(120) DEFAULT NULL,
  `pay_url` varchar(255) DEFAULT NULL,
  `checkout_url` varchar(255) DEFAULT NULL,
  `qr_url` varchar(255) DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `credited` tinyint(4) NOT NULL DEFAULT 0,
  `catatan` varchar(200) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saldo_topup`
--

INSERT INTO `saldo_topup` (`id`, `merchant_ref`, `wali_id`, `santri_id`, `nominal`, `metode`, `metode_nama`, `reference`, `status`, `total_bayar`, `biaya`, `pay_code`, `pay_url`, `checkout_url`, `qr_url`, `expired_at`, `paid_at`, `credited`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 'TU2610241021543571CF', 1, 26, 10000, 'BNIVA', 'BNI Virtual Account', 'DEV-T242644136788E7RM', 'PAID', 18500, 4250, '291954794265035', NULL, 'https://tripay.co.id/checkout/DEV-T242644136788E7RM', NULL, '2026-10-25 10:20:55', '2026-10-24 10:28:56', 1, 'Dikonfirmasi manual oleh Administrator', '2026-10-24 10:21:54', '2026-10-24 10:28:56'),
(2, 'TU261024141445A9B088', 1, 23, 50000, 'BNIVA', 'BNI Virtual Account', 'DEV-T242644137243TXHU', 'PAID', 58500, 4250, '817288036999938', NULL, 'https://tripay.co.id/checkout/DEV-T242644137243TXHU', NULL, '2026-10-25 14:13:44', '2026-10-24 16:10:32', 1, 'Dikonfirmasi manual oleh Administrator', '2026-10-24 14:14:45', '2026-10-24 16:10:32');

-- --------------------------------------------------------

--
-- Table structure for table `saldo_transaksi`
--

CREATE TABLE `saldo_transaksi` (
  `id` int(11) NOT NULL,
  `santri_id` int(11) NOT NULL,
  `tipe` varchar(10) NOT NULL,
  `jumlah` bigint(20) NOT NULL,
  `saldo_sebelum` bigint(20) NOT NULL,
  `saldo_sesudah` bigint(20) NOT NULL,
  `waktu` datetime NOT NULL,
  `petugas` varchar(100) NOT NULL DEFAULT '',
  `catatan` varchar(200) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saldo_transaksi`
--

INSERT INTO `saldo_transaksi` (`id`, `santri_id`, `tipe`, `jumlah`, `saldo_sebelum`, `saldo_sesudah`, `waktu`, `petugas`, `catatan`) VALUES
(1, 11, 'setor', 100000, 0, 100000, '2026-10-17 11:04:18', 'Administrator', 'Setor tunai'),
(2, 11, 'setor', 10000, 100000, 110000, '2026-10-17 11:06:28', 'Administrator', 'Setor tunai'),
(3, 1, 'setor', 10000, 0, 10000, '2026-10-17 11:06:43', 'Administrator', 'Setor tunai'),
(4, 1, 'tarik', 5000, 10000, 5000, '2026-10-17 11:09:22', 'Administrator', 'Tarik tunai di kiosk'),
(5, 1, 'tarik', 5000, 5000, 0, '2026-10-17 11:09:36', 'Administrator', 'Tarik tunai di kiosk'),
(6, 1, 'setor', 1000000, 0, 1000000, '2026-10-17 11:10:43', 'Administrator', 'Setor tunai'),
(7, 1, 'tarik', 100000, 1000000, 900000, '2026-10-17 11:11:00', 'Administrator', 'Tarik tunai di kiosk'),
(8, 26, 'setor', 100000, 0, 100000, '2026-10-21 14:03:33', 'Administrator', 'Setor tunai'),
(9, 26, 'tarik', 10000, 100000, 90000, '2026-10-21 14:06:25', 'Administrator', 'Tarik tunai di kiosk'),
(10, 26, 'tarik', 10000, 90000, 80000, '2026-10-21 14:06:37', 'Administrator', 'Tarik tunai di kiosk'),
(11, 26, 'tarik', 10000, 80000, 70000, '2026-10-21 14:07:49', 'Administrator', 'Tarik tunai di kiosk'),
(12, 26, 'tarik', 5000, 70000, 65000, '2026-10-21 14:49:41', 'kayla', 'Tarik tunai di kiosk'),
(13, 26, 'tarik', 5000, 65000, 60000, '2026-10-21 14:52:00', 'kayla', 'Tarik tunai di kiosk'),
(14, 29, 'setor', 100000, 0, 100000, '2026-10-21 16:30:34', 'Administrator', 'Setor tunai'),
(15, 25, 'setor', 100000, 0, 100000, '2026-10-21 16:30:40', 'Administrator', 'Setor tunai'),
(16, 27, 'setor', 100000, 0, 100000, '2026-10-21 16:30:45', 'Administrator', 'Setor tunai'),
(17, 31, 'setor', 100000, 0, 100000, '2026-10-21 16:30:51', 'Administrator', 'Setor tunai'),
(18, 28, 'setor', 100000, 0, 100000, '2026-10-21 16:31:04', 'Administrator', 'Setor tunai'),
(19, 23, 'setor', 100000, 0, 100000, '2026-10-21 16:31:10', 'Administrator', 'Setor tunai'),
(20, 23, 'tarik', 20000, 100000, 80000, '2026-10-21 16:48:12', 'Administrator', 'Tarik tunai di kiosk'),
(21, 23, 'tarik', 10000, 80000, 70000, '2026-10-21 16:48:22', 'Administrator', 'Tarik tunai di kiosk'),
(22, 23, 'tarik', 5000, 70000, 65000, '2026-10-21 16:55:35', 'kayla', 'Tarik tunai di kiosk'),
(23, 26, 'setor', 10000, 60000, 70000, '2026-10-24 10:28:56', 'Wali (Tripay)', 'Top up via BNI Virtual Account (TU2610241021543571CF)'),
(24, 26, 'tarik', 10000, 70000, 60000, '2026-10-24 14:12:44', 'kayla', 'Tarik tunai di kiosk'),
(25, 23, 'setor', 50000, 65000, 115000, '2026-10-24 16:10:32', 'Wali (Tripay)', 'Top up via BNI Virtual Account (TU261024141445A9B088)'),
(26, 26, 'tarik', 10000, 60000, 50000, '2026-10-24 16:38:30', 'Administrator', 'Tarik tunai ');

-- --------------------------------------------------------

--
-- Table structure for table `saldo_wali`
--

CREATE TABLE `saldo_wali` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `hp` varchar(30) NOT NULL DEFAULT '',
  `email` varchar(120) NOT NULL DEFAULT '',
  `password_hash` varchar(255) NOT NULL,
  `aktif` tinyint(4) NOT NULL DEFAULT 1,
  `gagal` int(11) NOT NULL DEFAULT 0,
  `terkunci_sampai` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `probe_gagal` int(11) NOT NULL DEFAULT 0,
  `probe_mulai` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saldo_wali`
--

INSERT INTO `saldo_wali` (`id`, `username`, `nama`, `hp`, `email`, `password_hash`, `aktif`, `gagal`, `terkunci_sampai`, `last_login`, `created_at`, `probe_gagal`, `probe_mulai`) VALUES
(1, 'sri.santi03', 'santi', '083856731371', '', '$2y$10$X9/cLsw7JYq2oF/E38pwjOz8sIO4WhqInyB4scq1dWkoXQ2zbzOTW', 1, 0, NULL, '2026-10-24 16:27:06', '2026-10-22 16:09:25', 0, NULL),
(2, 'testing', 'testing', '083567887656', '', '$2y$10$RzNXOmr4hgm3q1fnyg5YquKZAIgv4TyDvgrFmPaqBAyNGP6qs/80K', 1, 0, NULL, '2026-10-22 16:39:56', '2026-10-22 16:38:08', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `saldo_wali_santri`
--

CREATE TABLE `saldo_wali_santri` (
  `wali_id` int(11) NOT NULL,
  `santri_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saldo_wali_santri`
--

INSERT INTO `saldo_wali_santri` (`wali_id`, `santri_id`) VALUES
(1, 23),
(1, 26),
(1, 29),
(2, 25);

-- --------------------------------------------------------

--
-- Table structure for table `santri`
--

CREATE TABLE `santri` (
  `id` int(11) NOT NULL,
  `nomor` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kelas` varchar(30) NOT NULL DEFAULT '',
  `kelompok` varchar(10) NOT NULL DEFAULT 'QBS',
  `uid_kartu` varchar(64) DEFAULT NULL,
  `foto` varchar(100) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'aktif',
  `created_at` datetime DEFAULT NULL,
  `ruang` varchar(10) NOT NULL DEFAULT 'QBS'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `santri`
--

INSERT INTO `santri` (`id`, `nomor`, `nama`, `kelas`, `kelompok`, `uid_kartu`, `foto`, `status`, `created_at`, `ruang`) VALUES
(23, '7', 'Yumna', '11', 'akhwat', '0010142008', NULL, 'aktif', '2026-10-18 14:19:35', 'FQ'),
(25, '2', 'husna aulia', '10', 'akhwat', '10992711', NULL, 'aktif', '2026-10-18 16:36:44', 'FQ'),
(26, '3', 'ayla sarah', '7', 'akhwat', '0010986384', NULL, 'aktif', '2026-10-18 16:36:44', 'FQ'),
(27, '4', 'kinzi', '7', 'akhwat', '0010159319', NULL, 'aktif', '2026-10-18 16:36:44', 'FQ'),
(28, '5', 'raihanah', '7', 'akhwat', '11039170', NULL, 'aktif', '2026-10-18 16:36:44', 'FQ'),
(29, '6', 'Hafidzah', '10', 'akhwat', '0010180679', NULL, 'aktif', '2026-10-18 16:37:52', 'FQ'),
(31, '8', 'Nasywa', '10', 'ikhwan', '0010173422', NULL, 'aktif', '2026-10-21 10:42:15', 'QBS');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'petugas',
  `ruang` varchar(10) NOT NULL DEFAULT 'all',
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `nama`, `password_hash`, `role`, `ruang`, `created_at`) VALUES
(1, 'admin', 'Administrator', '$2y$10$lxyNZsr3Iqk6l0QoPfHKneUhR3dVn4rQ2uZuqvRfqLQR2QKNcnibC', 'admin', 'all', '2026-10-14 14:22:48'),
(2, 'penjaga', 'kayla', '$2y$10$s4NevpxsikUorNt6UfqnGO.ufxGlWm.mGVFWGHPlzHszurHKVrOH6', 'petugas', 'all', '2026-10-14 21:45:39');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL,
  `login_at` datetime NOT NULL,
  `last_seen` datetime NOT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'online',
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `username`, `nama`, `role`, `login_at`, `last_seen`, `status`, `ip_address`, `user_agent`) VALUES
(1, 1, 'admin', 'Administrator', 'admin', '2026-10-15 17:31:04', '2026-10-24 16:40:07', 'online', '::1', 'Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36'),
(5, 2, 'penjaga', 'kayla', 'petugas', '2026-10-15 17:31:44', '2026-10-24 14:03:03', 'online', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_pinjam_santri` (`santri_id`,`jenis`,`waktu_kembali`),
  ADD KEY `ix_pinjam_waktu` (`waktu_pinjam`);

--
-- Indexes for table `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`k`);

--
-- Indexes for table `saldo_akun`
--
ALTER TABLE `saldo_akun`
  ADD PRIMARY KEY (`santri_id`);

--
-- Indexes for table `saldo_log`
--
ALTER TABLE `saldo_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `saldo_pengaturan`
--
ALTER TABLE `saldo_pengaturan`
  ADD PRIMARY KEY (`k`);

--
-- Indexes for table `saldo_remember`
--
ALTER TABLE `saldo_remember`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `ix_rem_user` (`tipe`,`user_id`);

--
-- Indexes for table `saldo_topup`
--
ALTER TABLE `saldo_topup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `merchant_ref` (`merchant_ref`),
  ADD KEY `ix_topup_wali` (`wali_id`,`id`),
  ADD KEY `ix_topup_santri` (`santri_id`),
  ADD KEY `ix_topup_status` (`status`);

--
-- Indexes for table `saldo_transaksi`
--
ALTER TABLE `saldo_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_saldo_trx_santri` (`santri_id`,`waktu`),
  ADD KEY `ix_saldo_trx_waktu` (`waktu`);

--
-- Indexes for table `saldo_wali`
--
ALTER TABLE `saldo_wali`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `saldo_wali_santri`
--
ALTER TABLE `saldo_wali_santri`
  ADD PRIMARY KEY (`wali_id`,`santri_id`),
  ADD KEY `ix_wali_santri_santri` (`santri_id`);

--
-- Indexes for table `santri`
--
ALTER TABLE `santri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor` (`nomor`),
  ADD UNIQUE KEY `uid_kartu` (`uid_kartu`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `saldo_log`
--
ALTER TABLE `saldo_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `saldo_remember`
--
ALTER TABLE `saldo_remember`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `saldo_topup`
--
ALTER TABLE `saldo_topup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `saldo_transaksi`
--
ALTER TABLE `saldo_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `saldo_wali`
--
ALTER TABLE `saldo_wali`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `santri`
--
ALTER TABLE `santri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=768;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `fk_pinjam_santri` FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

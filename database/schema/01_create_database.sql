-- =====================================================================
-- SplitWise+ | 01_create_database.sql
-- Target: MySQL 8.0 (InnoDB, utf8mb4). Re-runnable: drops and recreates.
-- =====================================================================
SET NAMES utf8mb4;
DROP DATABASE IF EXISTS splitwise_plus;

CREATE DATABASE splitwise_plus
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

USE splitwise_plus;

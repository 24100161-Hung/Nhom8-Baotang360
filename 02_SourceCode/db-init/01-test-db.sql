-- Chạy một lần khi khởi tạo volume MySQL: tạo CSDL kiểm thử cho PHPUnit và cấp quyền cho tài khoản ứng dụng.
CREATE DATABASE IF NOT EXISTS baotang360_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON baotang360_test.* TO 'baotang360'@'%';
-- CSDL kiểm thử phụ cho các luồng chạy song song (baotang360_test_1.._4)
CREATE DATABASE IF NOT EXISTS baotang360_test_1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS baotang360_test_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS baotang360_test_3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS baotang360_test_4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `baotang360\_test\_%`.* TO 'baotang360'@'%';
FLUSH PRIVILEGES;

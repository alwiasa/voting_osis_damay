<?php
// ==========================================
// PROSES LOGOUT (KELUAR AKUN)
// ==========================================

// Memulai sesi PHP yang sedang aktif agar data sesi pengguna dapat diakses
session_start();

// Menghapus seluruh variabel sesi (session variables) yang sedang tersimpan
session_unset();

// Menghancurkan sesi secara total di server untuk mengakhiri status login pengguna
session_destroy();

// Mengalihkan (redirect) peramban kembali ke halaman login.php
header("Location: login.php");

// Menghentikan eksekusi skrip lebih lanjut setelah proses pengalihan halaman
exit();
?>
<?php
// ==========================================
// KONFIGURASI KONEKSI DATABASE
// ==========================================

// Menentukan informasi server database
$host = "localhost";        // Nama host/server database (biasanya localhost untuk server lokal)
$user = "root";             // Username untuk mengakses database MySQL (default XAMPP/Laragon)
$pass = "";                 // Password database (secara default dikosongkan pada server lokal)
$db   = "db_voting_alwi";   // Nama database yang kita gunakan untuk proyek voting ini

// Menghubungkan skrip PHP dengan database MySQL menggunakan fungsi mysqli_connect
$koneksi = mysqli_connect($host, $user, $pass, $db);

// Pengecekan status koneksi database
if (!$koneksi) {
    // Jika koneksi gagal, hentikan eksekusi program (die) dan tampilkan pesan error dari sistem
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}
?>
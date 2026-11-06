<?php
// ==========================================
// 1. PENGATURAN SESI DAN OTENTIKASI PEMILIH
// ==========================================

// Memulai sesi PHP untuk melacak data login pengguna
session_start();

// Mengecek apakah pengguna belum login atau peran (role) yang masuk bukan sebagai 'pemilih'
// Jika salah satu kondisi terpenuhi, alihkan (redirect) kembali ke halaman login.php
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pemilih') {
    header("Location: login.php");
    exit(); // Menghentikan eksekusi skrip lebih lanjut
}

// Menghubungkan file koneksi.php agar sistem terhubung ke database MySQL
include '../koneksi.php';

// ==========================================
// 2. PROSES PENYIMPANAN DATA VOTE KE DATABASE
// ==========================================

// Mengambil ID kandidat dari parameter URL (metode GET) dan ID pemilih dari sesi yang sedang aktif
$id_kandidat = $_GET['id'] ?? null;
$id_pemilih  = $_SESSION['id_user'];

// Mengecek apakah parameter ID kandidat benar-benar tersedia (tidak kosong)
if ($id_kandidat) {
    
    // LANGKAH 1: Menambahkan (increment) total perolehan suara kandidat sebanyak 1 suara di tabel 'kandidat'
    $update_kandidat = mysqli_query($koneksi, "UPDATE kandidat SET total_suara = total_suara + 1 WHERE id_kandidat='$id_kandidat'");
    
    // LANGKAH 2: Mencatat riwayat pilihan ke dalam tabel 'vote' agar pemilih tidak bisa memilih dua kali
    $tanggal = date('Y-m-d H:i:s'); // Mengambil format tanggal dan waktu saat ini
    $insert_vote = mysqli_query($koneksi, "INSERT INTO vote (id_pemilih, id_kandidat, tanggal_vote) VALUES ('$id_pemilih', '$id_kandidat', '$tanggal')");

    // Mengecek apakah kedua proses query database di atas berhasil dijalankan tanpa kendala
    if ($update_kandidat && $insert_vote) {
        // Jika sukses, langsung alihkan halaman kembali ke halaman utama pemilih (pemilih.php)
        // Tanpa memunculkan pop-up alert agar transisi halaman mulus tanpa kendala layar abu-abu
        header("Location: pemilih.php");
        exit();
    } else {
        // Jika query gagal, tetap alihkan halaman ke pemilih.php untuk mencegah error atau macet pada sistem
        header("Location: pemilih.php");
        exit();
    }
} else {
    // Jika parameter ID kandidat di URL tidak ada, langsung alihkan halaman kembali ke pemilih.php
    header("Location: pemilih.php");
    exit();
}
?>
<?php
// Mengaktifkan pelaporan error untuk keperluan debugging/mencari kesalahan
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Menghubungkan file koneksi database agar terhubung ke MySQL
include '../koneksi.php';

// Mengecek apakah parameter 'id' tersedia di URL (dikirim melalui metode GET)
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Mengambil data nama foto dari database berdasarkan id_kandidat yang dipilih
    $query = mysqli_query($koneksi, "SELECT foto FROM kandidat WHERE id_kandidat='$id'");
    
    // Mengecek apakah data ditemukan di database
    if ($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_assoc($query);

        // Mengecek apakah kolom foto tidak kosong dan file fisik gambarnya benar-benar ada di folder 'uploads/'
        if (!empty($data['foto']) && file_exists('uploads/' . $data['foto'])) {
            // Menghapus file fisik foto tersebut dari server agar tidak memenuhi penyimpanan
            unlink('uploads/' . $data['foto']);
        }

        // Menghapus baris/record data kandidat secara permanen dari tabel database
        mysqli_query($koneksi, "DELETE FROM kandidat WHERE id_kandidat='$id'");
    }
}

// Mengalihkan halaman kembali ke dashboard admin setelah proses hapus selesai
header("Location: dashboard.php");
exit();
?>
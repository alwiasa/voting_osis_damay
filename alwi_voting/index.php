<?php
/**
 * =====================================================================
 * PENGALIHAN HALAMAN (REDIRECT)
 * =====================================================================
 * digunakan untuk mengarahkan atau memaksa browser pengguna 
 * agar pindah secara otomatis ke halaman "login.php".
 */
header("Location: login.php");

/**
 * Menghentikan eksekusi skrip PHP selanjutnya.
 * Sangat penting ditulis setelah header("Location: ...") agar kode 
 * di bawahnya tidak ikut dijalankan oleh server demi keamanan.
 */
exit();
?>
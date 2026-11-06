<?php
// Set waktu dan memori agar kuat memproses data besar
set_time_limit(0);
ini_set('memory_limit', '1024M');

// Hubungkan ke database
include '../koneksi.php';

// Jalankan proses hanya setelah admin mengirim form impor.
if (isset($_POST['proses_import'])) {
    // Ambil lokasi sementara file yang diunggah oleh PHP.
    $file_tmp = $_FILES['file_csv']['tmp_name'];

    if ($file_tmp) {
        // Buka file untuk dibaca dan siapkan penghitung data yang berhasil.
        $handle = fopen($file_tmp, "r");
        
        $jumlah_sukses = 0;
        $row_no = 0;
        
        // Mulai transaksi agar seluruh proses impor dapat disimpan atau dibatalkan bersama.
        mysqli_begin_transaction($koneksi);
        
        try {
            // Baca CSV per baris dengan titik koma sebagai pemisah kolom.
            while (($data = fgetcsv($handle,0, ";")) !== FALSE) {
                $row_no++;
                
                // Lewati baris pertama jika kolom kedua bukan data password/NIS.
                if ($row_no == 1 && !is_numeric($data[1])) {
                    continue;
                }

                // Gunakan kolom pertama sebagai username dan kolom kedua sebagai password.
                $username = isset($data[0]) ? trim($data[0]) : '';
                $password = isset($data[1]) ? trim($data[1]) : '';

                if (!empty($username) && !empty($password)) {
                    // Amankan nilai CSV sebelum dimasukkan ke query SQL.
                    $username_esc = mysqli_real_escape_string($koneksi, $username);
                    $password_esc = mysqli_real_escape_string($koneksi, $password);
                    
                    // Simpan akun baru dan abaikan username yang sudah terdaftar.
                    $query = "INSERT IGNORE INTO pemilih (username, password) VALUES ('$username_esc', '$password_esc')";
                    if (mysqli_query($koneksi, $query)) {
                        if (mysqli_affected_rows($koneksi) > 0) {
                            $jumlah_sukses++;
                        }
                    }
                }
            }
            fclose($handle);
            
            // Simpan seluruh perubahan setelah pembacaan file selesai.
            mysqli_commit($koneksi);
            
            echo "<script>alert('Berhasil mengimport $jumlah_sukses data pemilih secara masif!'); window.location='import_pemilih.php';</script>";
            
        } catch (Exception $e) {
            // Batalkan perubahan database jika terjadi error saat proses impor.
            mysqli_rollback($koneksi);
            fclose($handle);
            echo "<script>alert('Terjadi kesalahan saat import: " . $e->getMessage() . "');</script>";
        }

    } else {
        echo "<script>alert('Gagal mengupload file!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import Data Pemilih</title>
    <!-- Muat Bootstrap untuk tata letak dan gaya form impor. -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
    <div class="container" style="max-width: 500px;">
        <div class="card shadow p-4">
            <h3 class="mb-3">Import Data Pemilih (CSV)</h3>
            
            <!-- Form mengirim file CSV ke halaman ini untuk diproses. -->
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="file_csv" class="form-label">Pilih File CSV (.csv)</label>
                    <input type="file" name="file_csv" id="file_csv" class="form-control" accept=".csv" required>
                    <div class="form-text">Pastikan format file CSV menggunakan pemisah titik koma (;).</div>
                </div>
                <!-- Tombol ini mengirim file dan memulai proses impor. -->
                <button type="submit" name="proses_import" class="btn btn-success w-100">Upload dan Import Sekaligus</button>
            </form>
            <div class="mt-3">
                <!-- Kembali ke halaman utama pengelolaan pemilih. -->
                <a href="dashboard.php" class="btn btn-secondary btn-sm">&larr; Kembali ke Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
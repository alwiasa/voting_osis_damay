<?php
// ==========================================
// 1. PENGATURAN AWAL DAN KONEKSI DATABASE
// ==========================================

// Mengaktifkan pelaporan semua error PHP untuk membantu proses debugging jika ada kesalahan kode
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Menghubungkan file koneksi.php agar website terhubung ke database MySQL
include '../koneksi.php';

// Inisialisasi variabel untuk menampung pesan kesalahan (error)
$error_msg = '';

// ==========================================
// 2. PROSES KETIKA FORM DISUBMIT/DIKERJAKAN
// ==========================================

// Mengecek apakah tombol dengan 'name="submit"' pada form telah diklik oleh user
if (isset($_POST['submit'])) {
    
    // Menangkap data nomor urut dari form input
    $nomor_urut    = $_POST['nomor_urut'];
    
    // Menangkap data nama kandidat dan membersihkannya dari potensi celah keamanan SQL Injection
    $nama_kandidat = mysqli_real_escape_string($koneksi, trim($_POST['nama_kandidat']));
    
    // Menangkap data Visi dan Misi dari form input
    $visi_misi     = mysqli_real_escape_string($koneksi, trim($_POST['visi_misi']));
    
    // Mengambil ID admin yang sedang login dari session. Jika sesi tidak ada, gunakan nilai default 1
    $id_admin      = $_SESSION['id_user'] ?? 1;

    // Menangkap informasi file foto yang diunggah melalui form (nama file dan lokasi penyimpanan sementara/tmp)
    $foto       = $_FILES['foto']['name'];
    $tmp_name   = $_FILES['foto']['tmp_name'];
    
    // Membuat nama file foto baru yang unik menggunakan fungsi time() untuk menghindari duplikasi nama file
    // Serta membersihkan karakter khusus yang tidak aman menggunakan regex preg_replace
    $foto_baru  = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $foto);

    // Mengecek apakah folder 'uploads' sudah ada di dalam server. Jika belum, buat folder tersebut secara otomatis
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true); // Hak akses 0777 memberikan izin baca, tulis, dan eksekusi
    }

    // Mengecek apakah kolom file foto tidak kosong (artinya user benar-benar memilih file)
    if (!empty($foto)) {
        
        // Memindahkan file foto dari direktori sementara (tmp) di server ke folder tujuan permanen ('uploads/')
        if (move_uploaded_file($tmp_name, 'uploads/' . $foto_baru)) {
            
            // Menyusun query SQL untuk memasukkan (insert) data kandidat baru ke dalam tabel database 'kandidat'
            // CATATAN: Pastikan tabel 'kandidat' di database Anda sudah memiliki kolom 'visi_misi'
            $sql = "INSERT INTO kandidat (nomor_urut, nama_kandidat, visi_misi, foto, id_admin) 
                    VALUES ('$nomor_urut', '$nama_kandidat', '$visi_misi', '$foto_baru', '$id_admin')";

            // Menjalankan query SQL ke database
            if (mysqli_query($koneksi, $sql)) {
                // Jika proses simpan ke database berhasil, alihkan (redirect) halaman kembali ke dashboard admin
                header("Location: dashboard.php");
                exit(); // Menghentikan eksekusi skrip lebih lanjut setelah redirect
            } else {
                // Jika query database gagal dieksekusi, simpan pesan error-nya
                $error_msg = "Error Database: " . mysqli_error($koneksi);
            }
        } else {
            // Jika fungsi pemindahan file foto gagal dijalankan oleh server
            $error_msg = "Gagal mengunggah foto. Pastikan folder 'uploads' tersedia.";
        }
    } else {
        // Jika user menekan tombol simpan tanpa memilih file foto sama sekali
        $error_msg = "Silakan pilih foto kandidat terlebih dahulu.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kandidat Ketua OSIS - Premium</title>
    
    <!-- Memuat Google Font 'Poppins' untuk estetika tipografi tulisan -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Memuat Bootstrap 5 CSS untuk mempercepat styling komponen dasar halaman -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* Mengatur font global dan menghilangkan margin/padding bawaan browser */
        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Mengatur tata letak latar belakang halaman dengan gradasi warna menarik dan posisi tengah */
        body {
            background: linear-gradient(135deg, #065f46 0%, #0d9488 50%, #0284c7 100%);
            color: #333;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Desain kartu container utama form menggunakan efek kaca transparan (glassmorphism) */
        .card-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 520px;
            padding: 40px 35px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            overflow: hidden;
        }

        /* Membuat garis dekoratif gradasi warna di bagian paling atas kartu form */
        .card-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #059669, #0d9488, #0284c7);
        }

        /* Styling kotak ikon dekoratif yang terletak di atas judul form */
        .form-badge {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #10b981, #0d9488);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            color: white;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.4);
        }

        .card-custom h2 {
            font-size: 22px;
            color: #0f172a;
            margin-bottom: 8px;
            text-align: center;
            font-weight: 700;
        }

        .card-subtitle {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .input-group-text {
            background-color: #f8fafc;
            border: 2px solid #e2e8f0;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #64748b;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background-color: #f8fafc;
            outline: none;
            transition: all 0.25s ease;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
            background-color: #f8fafc;
        }

        /* Efek fokus saat kolom input diklik oleh pengguna */
        .form-control:focus {
            background-color: #fff;
            border-color: #0d9488;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
        }

        input[type="file"].form-control {
            padding: 10px;
            background: #f8fafc;
            cursor: pointer;
            border-radius: 12px;
            border-left: 2px solid #e2e8f0;
        }

        /* Styling kotak untuk pratinjau (preview) foto */
        .preview-container {
            width: 100px;
            height: 120px;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8fafc;
            margin-top: 10px;
            position: relative;
        }

        .preview-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .preview-text {
            font-size: 11px;
            color: #94a3b8;
            text-align: center;
            padding: 5px;
        }

        .btn-group-custom {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        /* Styling tombol simpan data dengan efek gradasi hijau-teal */
        .btn-simpan {
            background: linear-gradient(135deg, #059669, #0d9488);
            color: #fff;
            box-shadow: 0 8px 20px -4px rgba(5, 150, 105, 0.4);
        }

        .btn-simpan:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px -4px rgba(13, 148, 136, 0.5);
            background: linear-gradient(135deg, #047857, #0f766e);
            color: #fff;
        }

        /* Styling tombol batal untuk kembali ke halaman dashboard */
        .btn-batal {
            background-color: #f1f5f9;
            color: #475569;
            border: 2px solid #e2e8f0;
        }

        .btn-batal:hover {
            background-color: #e2e8f0;
            color: #1e293b;
        }

        /* Styling kotak peringatan (alert) jika terjadi kesalahan input atau sistem */
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="card-custom">
    <!-- Bagian Ikon Logo Visual di atas Form -->
    <div class="form-badge">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <line x1="19" y1="8" x2="19" y2="14"></line>
            <line x1="22" y1="11" x2="16" y2="11"></line>
        </svg>
    </div>

    <h2>Tambah Kandidat</h2>
    <p class="card-subtitle">Masukkan data kandidat ketua Osis</p>

    <!-- Pengecekan Kondisi: Menampilkan kotak pesan error jika variabel $error_msg terisi data -->
    <?php if (!empty($error_msg)) : ?>
        <div class="alert-error"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <!-- Form HTML untuk pengisian data kandidat. 
         Atribut 'enctype="multipart/form-data"' wajib ada agar form bisa memproses unggahan file/berkas foto -->
    <form action="" method="POST" enctype="multipart/form-data">
        
        <!-- Kolom Input untuk Nomor Urut Kandidat -->
        <div class="mb-3">
            <label for="nomor_urut" class="form-label">Nomor Urut</label>
            <div class="input-group">
                <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
                </span>
                <input type="number" id="nomor_urut" name="nomor_urut" class="form-control" placeholder="Contoh: 1" required>
            </div>
        </div>

        <!-- Kolom Input untuk Nama Lengkap Kandidat -->
        <div class="mb-3">
            <label for="nama_kandidat" class="form-label">Nama Kandidat</label>
            <div class="input-group">
                <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </span>
                <input type="text" id="nama_kandidat" name="nama_kandidat" class="form-control" placeholder="Masukkan nama lengkap" required autocomplete="off">
            </div>
        </div>

        <!-- Kolom Input Visi & Misi Kandidat -->
        <div class="mb-3">
            <label for="visi_misi" class="form-label">Visi & Misi</label>
            <textarea id="visi_misi" name="visi_misi" class="form-control" rows="4" placeholder="Masukkan Visi dan Misi kandidat..." required style="border-radius: 12px;"></textarea>
        </div>

        <!-- Kolom Input Berkas Foto Kandidat & Preview Foto -->
        <div class="mb-3">
            <label for="foto" class="form-label">Foto Kandidat</label>
            <input type="file" id="foto" name="foto" class="form-control" accept="image/*" required onchange="previewImage(event)">
            
            <!-- Kotak Pratinjau Foto -->
            <div class="preview-container">
                <span id="previewText" class="preview-text">Preview Foto</span>
                <img id="imgPreview" src="#" alt="Pratinjau Foto">
            </div>
        </div>

        <!-- Bagian Tombol Aksi (Tombol Batal dan Tombol Simpan) -->
        <div class="btn-group-custom">
            <a href="dashboard.php" class="btn btn-batal">Batal</a>
            <button type="submit" name="submit" class="btn btn-simpan">Simpan Kandidat</button>
        </div>
    </form>
</div>

<!-- Script JavaScript untuk memunculkan gambar saat dipilih -->
<script>
    function previewImage(event) {
        const reader = new FileReader();
        const imgPreview = document.getElementById('imgPreview');
        const previewText = document.getElementById('previewText');
        
        reader.onload = function() {
            imgPreview.src = reader.result;
            imgPreview.style.display = 'block';
            previewText.style.display = 'none';
        }
        
        if(event.target.files[0]) {
            reader.readAsDataURL(event.target.files[0]);
        }
    }
</script>

</body>
</html>
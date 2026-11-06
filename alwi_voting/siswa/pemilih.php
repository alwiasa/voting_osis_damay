<?php
// ==========================================
// 1. PENGATURAN SESI DAN KONEKSI DATABASE
// ==========================================

// Memulai sesi PHP untuk melacak status login pengguna yang sedang aktif
session_start();

// Mengecek apakah sesi username belum ada. 
// Jika belum ada, artinya pengguna belum login, maka alihkan (redirect) kembali ke halaman login.php
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit(); // Menghentikan eksekusi skrip lebih lanjut
}

// Menghubungkan file koneksi.php agar website terhubung ke database MySQL
include '../koneksi.php';

// Membaca status voting dari file teks buatan admin
$file_status = '../admin/status_voting.txt'; 
$status_voting_aktif = 1; // Default anggap buka
if (file_exists($file_status)) {
    $status_voting_aktif = intval(file_get_contents($file_status));
}

// Menyimpan data username dan ID pemilih yang sedang aktif dari variabel sesi (session)
$username = $_SESSION['username'];
$id_pemilih = $_SESSION['id_user'];

// ==========================================
// 2. PENGECEKAN STATUS VOTING PEMILIH
// ==========================================

// Mengecek ke database apakah siswa/pemilih ini sudah melakukan voting atau belum pada tabel 'vote'
$query_cek_vote = mysqli_query($koneksi, "SELECT * FROM vote WHERE id_pemilih = '$id_pemilih'");
$sudah_vote = mysqli_num_rows($query_cek_vote) > 0; // Bernilai true jika sudah ada data baris (sudah memilih)

// Inisialisasi variabel untuk menampung data kandidat yang dipilih
$data_kandidat_terpilih = null;

// Jika siswa sudah memilih, ambil data detail kandidat yang dipilih berdasarkan relasi ID kandidat pada tabel vote
if ($sudah_vote) {
    $data_vote = mysqli_fetch_assoc($query_cek_vote);
    $id_kandidat_terpilih = $data_vote['id_kandidat'];

    // Query untuk mengambil informasi detail kandidat yang dipilih berdasarkan ID-nya
    $query_terpilih = mysqli_query($koneksi, "SELECT * FROM kandidat WHERE id_kandidat = '$id_kandidat_terpilih'");
    $data_kandidat_terpilih = mysqli_fetch_assoc($query_terpilih);
}

// Jika siswa belum memilih, ambil seluruh daftar kandidat diurutkan dari nomor urut terkecil ke terbesar
$query_kandidat = mysqli_query($koneksi, "SELECT * FROM kandidat ORDER BY nomor_urut ASC");

// ------------------------------------------
// TAMBAHAN: MENGAMBIL DATA UNTUK GRAFIK / QUICK COUNT
// ------------------------------------------
$query_kandidat_grafik = mysqli_query($koneksi, "SELECT * FROM kandidat ORDER BY nomor_urut ASC");
$q_total_suara_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total_keseluruhan FROM vote");
$d_total_suara_siswa = mysqli_fetch_assoc($q_total_suara_siswa);
$total_suara_all_siswa = ($d_total_suara_siswa && $d_total_suara_siswa['total_keseluruhan'] > 0) ? $d_total_suara_siswa['total_keseluruhan'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voting Ketua OSIS - F-Voting</title>
    <!-- Memuat Google Font 'Poppins' untuk estetika tipografi tulisan -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Pengaturan dasar tata letak, ukuran box, dan tipografi halaman */
        * { box-sizing: border-box; font-family: 'Poppins', sans-serif; margin: 0; padding: 0; }
        
        /* Mengatur latar belakang halaman dengan gradasi warna menarik dan tinggi minimum layar */
        body { background: linear-gradient(135deg, #065f46 0%, #0d9488 50%, #0284c7 100%); color: #1e293b; padding: 40px 20px; min-height: 100vh; }
        .container { max-width: 950px; margin: 0 auto; }
        
        /* Bagian Header halaman (menyambut nama user yang login dan menyediakan tombol logout) */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: rgba(255, 255, 255, 0.95); padding: 24px 30px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15); }
        .header h2 { font-size: 22px; color: #0f172a; font-weight: 700; }
        .btn-logout { background: linear-gradient(135deg, #f87171, #ef4444); color: #fff; padding: 10px 18px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; }
        
        /* Styling Tampilan Kotak Kartu Jika User Sudah Melakukan Vote */
        .card-sudah-pilih { background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 30px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15); margin-bottom: 25px; }
        .alert-sudah { background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 14px 20px; border-radius: 14px; margin-bottom: 25px; font-weight: 600; display: inline-block; }
        .kandidat-terpilih-box { display: inline-block; background: #f8fafc; border: 2px solid #0d9488; border-radius: 16px; padding: 20px; position: relative; margin-top: 10px; max-width: 320px; width: 100%; }
        .badge-centang { position: absolute; top: -12px; right: -12px; background: #10b981; color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; border: 2px solid #fff; }
        
        /* Pengaturan ukuran foto dan tata letak kartu kandidat */
        .kandidat-terpilih-box img, .kandidat-card img { width: 110px; height: 140px; border-radius: 10px; object-fit: cover; margin-bottom: 12px; border: 2px solid #0d9488; }
        .kandidat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .kandidat-card { background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 20px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        
        /* Kotak khusus untuk menampilkan teks visi & misi dengan fungsi gulir (scroll) jika teksnya panjang */
        .visi-misi-box { font-size: 12px; color: #475569; text-align: left; background: #f8fafc; padding: 10px; border-radius: 8px; margin: 10px 0; max-height: 110px; overflow-y: auto; border: 1px solid #e2e8f0; }
        .btn-pilih { display: inline-block; background: #0d9488; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; margin-top: 5px; }
        .btn-pilih:hover { background: #0f766e; }
        .alert-tutup { background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; padding: 10px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-top: 5px; }

        /* Styling Kotak Panel Grafik / Quick Count di Halaman Siswa */
        .card-grafik-siswa { background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 30px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15); margin-top: 25px; }
    </style>
</head>
<body>

<div class="container">
    <!-- Bagian Header Profil Pengguna yang Sedang Login -->
    <div class="header">
        <div>
            <h2>Halo, <?php echo $username; ?>! 👋</h2>
            <p style="font-size: 14px; color: #64748b; margin-top: 4px;">Silakan tentukan calon Ketua OSIS pilihanmu dengan bijak.</p>
        </div>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>

    <!-- Pengecekan Kondisi 1: Jika user sudah melakukan voting -->
    <?php if ($sudah_vote) : ?>
        <div class="card-sudah-pilih">
            <div class="alert-sudah">✅ Terima kasih! Kamu sudah menggunakan hak pilihmu.</div>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">Kandidat yang telah kamu pilih:</p>

            <?php if ($data_kandidat_terpilih) : ?>
                <div class="kandidat-terpilih-box">
                    <div class="badge-centang">✓</div>
                    <h4 style="color: #0d9488; margin-bottom: 8px; font-size: 14px;">No. Urut <?php echo sprintf('%02d', $data_kandidat_terpilih['nomor_urut']); ?></h4>
                    
                    <!-- Pengecekan direktori letak file foto kandidat yang dipilih agar aman dari error folder -->
                    <?php if (!empty($data_kandidat_terpilih['foto']) && file_exists('../uploads/' . $data_kandidat_terpilih['foto'])) : ?>
                        <img src="../uploads/<?php echo $data_kandidat_terpilih['foto']; ?>" alt="Foto Kandidat">
                    <?php elseif (!empty($data_kandidat_terpilih['foto']) && file_exists('../admin/uploads/' . $data_kandidat_terpilih['foto'])) : ?>
                        <img src="../admin/uploads/<?php echo $data_kandidat_terpilih['foto']; ?>" alt="Foto Kandidat">
                    <?php else : ?>
                        <div style="width: 110px; height: 140px; border-radius: 10px; background: #e2e8f0; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; color: #64748b; font-size: 12px;">No Foto</div>
                    <?php endif; ?>

                    <h3 style="color: #0f172a; font-size: 16px; margin-bottom: 8px;"><?php echo $data_kandidat_terpilih['nama_kandidat']; ?></h3>
                    <div class="visi-misi-box">
                        <strong style="color: #0f172a; display: block; margin-bottom: 2px;">Visi & Misi:</strong>
                        <span><?php echo !empty($data_kandidat_terpilih['visi_misi']) ? nl2br($data_kandidat_terpilih['visi_misi']) : 'Belum ada visi & misi.'; ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    
    <!-- Pengecekan Kondisi 2: Jika user belum memilih, tampilkan daftar seluruh kandidat -->
    <?php else : ?>
        <div class="kandidat-grid">
            <!-- Perulangan (while) untuk mengambil dan menampilkan setiap data kandidat dari database secara dinamis -->
            <?php while ($row = mysqli_fetch_assoc($query_kandidat)) : ?>
                <div class="kandidat-card">
                    <div>
                        <h3 style="color: #0d9488; margin-bottom: 10px;">No. Urut <?php echo sprintf('%02d', $row['nomor_urut']); ?></h3>
                        
                        <!-- Pengecekan direktori letak file foto untuk setiap kandidat di dalam perulangan -->
                        <?php if (!empty($row['foto']) && file_exists('../uploads/' . $row['foto'])) : ?>
                            <img src="../uploads/<?php echo $row['foto']; ?>" alt="Foto Kandidat">
                        <?php elseif (!empty($row['foto']) && file_exists('../admin/uploads/' . $row['foto'])) : ?>
                            <img src="../admin/uploads/<?php echo $row['foto']; ?>" alt="Foto Kandidat">
                        <?php else : ?>
                            <div style="width: 120px; height: 150px; border-radius: 12px; background: #e2e8f0; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 15px; color: #64748b; font-size: 12px;">No Foto</div>
                        <?php endif; ?>

                        <h4 style="color: #0f172a; font-size: 18px; margin-bottom: 8px;"><?php echo $row['nama_kandidat']; ?></h4>
                        <div class="visi-misi-box">
                            <strong style="color: #0f172a; display: block; margin-bottom: 2px;">Visi & Misi:</strong>
                            <span><?php echo !empty($row['visi_misi']) ? nl2br($row['visi_misi']) : 'Belum ada visi & misi.'; ?></span>
                        </div>
                    </div>
                    <div>
                        <!-- Pengecekan status voting: Jika Buka (1) tampilkan tombol pilih, jika Tutup (0) sembunyikan tombol -->
                        <?php if ($status_voting_aktif == 1) : ?>
                            <a href="proses_pilih.php?id=<?php echo $row['id_kandidat']; ?>" class="btn-pilih">Pilih Kandidat</a>
                        <?php else : ?>
                            <div class="alert-tutup">Voting Ditutup oleh Admin</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <!-- PANEL GRAFIK / QUICK COUNT SEMENTARA DI HALAMAN SISWA -->
    <div class="card-grafik-siswa">
        <h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 5px; text-align: center;">Grafik Perolehan Suara Sementara</h3>
        <p style="font-size: 13px; color: #64748b; text-align: center; margin-bottom: 25px;">Hasil real-time dari suara siswa yang sudah masuk</p>

        <div style="display: flex; flex-direction: column; gap: 18px;">
            <?php while ($kg = mysqli_fetch_assoc($query_kandidat_grafik)) : ?>
                <?php 
                    $kid_g = $kg['id_kandidat'];
                    $q_s_g = mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM vote WHERE id_kandidat = '$kid_g'");
                    $d_s_g = mysqli_fetch_assoc($q_s_g);
                    $suara_k = $d_s_g ? $d_s_g['jml'] : 0;
                    
                    $persen_k = ($total_suara_all_siswa > 0) ? ($suara_k / $total_suara_all_siswa) * 100 : 0;
                ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        <span>No. Urut <?php echo sprintf('%02d', $kg['nomor_urut']); ?> - <?php echo htmlspecialchars($kg['nama_kandidat']); ?></span>
                        <span style="color: #0d9488;"><?php echo $suara_k; ?> Suara (<?php echo number_format($persen_k, 1); ?>%)</span>
                    </div>
                    <div style="width: 100%; background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden;">
                        <div style="width: <?php echo $persen_k; ?>%; background: linear-gradient(90deg, #059669, #0d9488); height: 100%; border-radius: 6px; transition: width 0.4s ease;"></div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <div style="margin-top: 30px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 15px;">
            <span style="font-size: 13px; color: #64748b; font-weight: 500;">Total Suara Masuk Seluruhnya: <strong style="color: #0f172a;"><?php echo $total_suara_all_siswa; ?> Pemilih</strong></span>
        </div>
    </div>
</div>

</body>
</html>
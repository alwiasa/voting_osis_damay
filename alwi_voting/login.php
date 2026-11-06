<?php
// ==========================================
// 1. PENGATURAN SESI DAN KONEKSI DATABASE
// ==========================================

// Memulai sesi PHP untuk melacak status login pengguna
session_start();        

// Menghubungkan file koneksi.php agar website terhubung ke database MySQL
include 'koneksi.php';

// Inisialisasi variabel untuk menampung pesan kesalahan (error)
$error = '';

// ==========================================
// 1.5. AMBIL DATA UNTUK STATISTIK DI PANEL KIRI
// ==========================================
// Ambil semua kandidat dan urutkan berdasarkan nomor urut terkecil lebih dahulu.
$q_jml_kandidat = mysqli_query($koneksi, "SELECT * FROM kandidat ORDER BY nomor_urut ASC");
// Siapkan array kosong untuk menyimpan semua data kandidat yang ditemukan.
$array_kandidat = [];
// Ambil setiap baris hasil query kandidat satu per satu.
while($row_k = mysqli_fetch_assoc($q_jml_kandidat)) {
    // Tambahkan data kandidat saat ini ke dalam array kandidat.
    $array_kandidat[] = $row_k;
}

// Hitung jumlah seluruh pemilih yang terdaftar di database.
$q_total_pemilih = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM pemilih");
// Ambil hasil hitung jumlah pemilih sebagai satu baris data.
$d_tp = mysqli_fetch_assoc($q_total_pemilih);
// Gunakan jumlah dari database, atau nol jika hasil query tidak tersedia.
$jumlah_pemilih_terdaftar = $d_tp ? $d_tp['total'] : 0;

// Hitung jumlah suara yang sudah tercatat di tabel vote.
$q_sudah_vote = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM vote");
// Ambil hasil hitung suara sebagai satu baris data.
$d_sv = mysqli_fetch_assoc($q_sudah_vote);
// Gunakan jumlah suara dari database, atau nol jika hasil query tidak tersedia.
$jumlah_sudah_vote = $d_sv ? $d_sv['total'] : 0;


// ==========================================
// 2. PROSES VALIDASI KETIKA FORM LOGIN DI-SUBMIT
// ==========================================

// Jalankan validasi hanya ketika formulir dikirim melalui tombol login.
if (isset($_POST['login'])) {
    // Baca jenis akun yang dipilih pada formulir login.
    // Kolom 1: role login, menentukan user masuk sebagai admin atau pemilih
    $role = $_POST['role'];

    // Rapikan spasi di username lalu amankan karakter khusus sebelum dipakai di query SQL.
    // Kolom 2: username yang diinput user, sesuai dengan field username di tabel admin/pemilih
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));

    // Baca kata sandi yang dikirim dari formulir.
    // Kolom 3: password yang diinput user, sesuai dengan field password di tabel admin/pemilih
    $password = $_POST['password'];

    // ------------------------------------------
    // A. VALIDASI LOGIN UNTUK ADMIN
    // ------------------------------------------
    // Pilih proses pemeriksaan akun sesuai jenis peran yang dipilih.
    if ($role == 'admin') {
        // Cari akun admin yang username-nya cocok dengan masukan pengguna.
        $query = mysqli_query($koneksi, "SELECT * FROM admin WHERE username='$username'");
        // Lanjutkan pemeriksaan hanya jika ditemukan tepat satu akun.
        // Periksa bahwa hasil pencarian hanya berisi satu akun admin.
        if (mysqli_num_rows($query) === 1) {
            // Ambil data akun admin yang cocok dari hasil query.
            $data = mysqli_fetch_assoc($query);
            // Bandingkan kata sandi yang dikirim dengan kata sandi pada data akun.
            // Pastikan kata sandi masukan sama persis dengan data akun.
            if ($password === $data['password']) { 
                // Simpan ID akun yang berhasil masuk pada sesi.
                $_SESSION['id_user']  = $data['id_admin'];
                // Simpan username akun pada sesi.
                $_SESSION['username'] = $data['username'];
                // Tandai bahwa akun yang aktif memiliki peran admin.
                $_SESSION['role']     = 'admin';
                // Arahkan admin yang berhasil masuk ke halaman dashboard.
                header("Location: admin/dashboard.php");
                // Hentikan proses agar halaman login tidak dilanjutkan.
                exit();
            } else {
                // Tampilkan pesan jika username benar tetapi kata sandinya tidak cocok.
                $error = 'Password Admin salah!';
            }
        } else {
            // Tampilkan pesan jika username tidak ditemukan atau hasilnya bukan satu akun.
            $error = 'Username Admin tidak terdaftar!';
        }
    } 
    // ------------------------------------------
    // B. VALIDASI LOGIN UNTUK SISWA / PEMILIH
    // ------------------------------------------
    // Jika peran bukan admin, jalankan pemeriksaan akun pemilih saat peran tersebut dipilih.
    else if ($role == 'pemilih') {
        // Cari akun pemilih yang username-nya cocok dengan masukan pengguna.
        $query = mysqli_query($koneksi, "SELECT * FROM pemilih WHERE username='$username'");
        // Lanjutkan pemeriksaan hanya jika ditemukan tepat satu akun pemilih.
        // Periksa bahwa hasil pencarian hanya berisi satu akun pemilih.
        if (mysqli_num_rows($query) === 1) {
            // Ambil data pemilih yang cocok dari hasil query.
            $data = mysqli_fetch_assoc($query);
            // Bandingkan kata sandi yang dikirim dengan kata sandi pada data akun.
            // Pastikan kata sandi masukan sama persis dengan data akun.
            if ($password === $data['password']) {
                // Simpan ID akun pemilih yang berhasil masuk pada sesi.
                $_SESSION['id_user']  = $data['id_pemilih'];
                // Simpan username pemilih pada sesi.
                $_SESSION['username'] = $data['username'];
                // Tandai bahwa akun yang aktif memiliki peran pemilih.
                $_SESSION['role']     = 'pemilih';
                // Arahkan pemilih yang berhasil masuk ke halaman pemilihan.
                header("Location: siswa/pemilih.php");
                // Hentikan proses agar halaman login tidak dilanjutkan.
                exit();
            } else {
                // Tampilkan pesan jika username benar tetapi kata sandinya tidak cocok.
                $error = 'Password Siswa salah!';
            }
        } else {
            // Tampilkan pesan jika username pemilih tidak ditemukan.
            $error = 'Username/Nama Siswa tidak terdaftar di database!';
        }
    }
}
?>

<!DOCTYPE html>
<!-- Dokumen memakai bahasa Indonesia untuk aksesibilitas dan pemrosesan browser. -->
<html lang="id">
<head>
    <!-- Atur pengkodean karakter agar teks bahasa Indonesia tampil dengan benar. -->
    <meta charset="UTF-8">
    <!-- Sesuaikan lebar halaman dengan perangkat untuk tampilan responsif. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tetapkan judul yang muncul pada tab browser. -->
    <title>Login Pemilihan Ketua OSIS - Live E-Voting</title>
    
    <!-- Memuat Google Font 'Poppins' -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Memuat Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Mulai aturan CSS khusus untuk tata letak, dekorasi, dan formulir login. -->
    <style>
        /* Terapkan font Poppins dan perhitungan ukuran box yang konsisten pada seluruh elemen. */
        * {
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
        }

        /* Buat latar gradasi memenuhi layar dan tempatkan konten utama tepat di tengah halaman. */
        body { 
            background: linear-gradient(135deg, #065f46 0%, #0d9488 50%, #0284c7 100%);
            min-height: 100vh; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            margin: 0; 
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        /* Atur wadah partikel sebagai lapisan dekoratif yang memenuhi layar tanpa menangkap klik. */
        .bg-particles {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }

        /* Beri setiap partikel bentuk, warna transparan, dan animasi naik dari bagian bawah layar. */
        .particle {
            position: absolute;
            display: block;
            list-style: none;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.15);
            animation: animateParticle 15s linear infinite;
            bottom: -150px;
            border-radius: 50%;
        }

        /* Atur posisi, ukuran, dan tempo animasi untuk partikel pertama. */
        .particle:nth-child(1) { left: 10%; width: 40px; height: 40px; animation-duration: 12s; }
        /* Atur posisi, ukuran, durasi, dan jeda animasi untuk partikel kedua. */
        .particle:nth-child(2) { left: 25%; width: 20px; height: 20px; animation-duration: 18s; animation-delay: 2s; }
        /* Atur posisi, ukuran, durasi, dan jeda animasi untuk partikel ketiga. */
        .particle:nth-child(3) { left: 40%; width: 50px; height: 50px; animation-duration: 10s; animation-delay: 4s; }
        /* Atur posisi, ukuran, dan jeda animasi untuk partikel keempat. */
        .particle:nth-child(4) { left: 60%; width: 25px; height: 25px; animation-duration: 15s; animation-delay: 1s; }
        /* Atur posisi, ukuran, durasi, dan jeda animasi untuk partikel kelima. */
        .particle:nth-child(5) { left: 75%; width: 35px; height: 35px; animation-duration: 20s; animation-delay: 3s; }
        /* Atur posisi, ukuran, durasi, dan jeda animasi untuk partikel keenam. */
        .particle:nth-child(6) { left: 90%; width: 45px; height: 45px; animation-duration: 14s; animation-delay: 5s; }

        /* Gerakkan partikel ke atas, putar, lalu hilangkan secara bertahap selama animasi berlangsung. */
        @keyframes animateParticle {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.8;
                border-radius: 50%;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
                border-radius: 20%;
            }
        }

        /* Susun panel kiri dan kartu login dengan jarak, ukuran maksimum, dan tata letak responsif. */
        .login-wrapper {
            display: flex;
            gap: 40px;
            max-width: 980px;
            width: 100%;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            z-index: 1;
        }

        /* Beri panel informasi ruang yang fleksibel, lebar minimum, warna teks, dan jarak dalam. */
        .interactive-panel {
            flex: 1;
            min-width: 320px;
            color: white;
            padding: 10px;
        }

        /* Bentuk label informasi live dengan latar transparan, efek blur, dan susunan ikon sebaris. */
        .live-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Tampilkan titik hijau kecil dengan lingkaran dan efek denyut sebagai penanda status live. */
        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #22c55e;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            animation: pulseAnimation 1.5s infinite;
        }

        /* Besarkan bayangan titik sementara, lalu kembalikan ke ukuran awal untuk efek berdenyut. */
        @keyframes pulseAnimation {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 8px rgba(34, 197, 94, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }

        /* Atur judul panel kiri agar tegas dan bergradasi, lalu animasikan kilau teksnya. */
        .interactive-panel h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            line-height: 1.2;
            background: linear-gradient(90deg, #ffffff, #a7f3d0, #ffffff);
            background-size: 200% auto;
            color: transparent;
            -webkit-background-clip: text;
            animation: shimmerText 4s linear infinite;
        }

        @keyframes shimmerText {
            to { background-position: 200% center; }
        }

        /* Atur ukuran dan jarak paragraf informasi pada panel kiri. */
        .interactive-panel p {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 15px;
        }

        /* Bentuk kotak pengumuman transparan yang menahan teks agar tetap satu baris dan tidak meluber. */
        .announcement-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            padding: 10px 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            overflow: hidden;
            white-space: nowrap;
        }

        /* Beri label INFO warna kontras, ukuran kecil, dan bentuk seperti lencana. */
        .announcement-label {
            background: #047857;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            flex-shrink: 0;
            letter-spacing: 0.5px;
        }

        /* Batasi area teks berjalan agar konten yang keluar dari wadah tidak terlihat. */
        .ticker-container {
            overflow: hidden;
            width: 100%;
        }

        /* Tampilkan teks pengumuman sebaris dan gerakkan terus-menerus secara horizontal. */
        .ticker-text {
            display: inline-block;
            font-size: 13px;
            font-weight: 500;
            color: #f0fdf4;
            animation: tickerScroll 18s linear infinite;
        }

        /* Geser teks dari sisi kanan ke sisi kiri wadah untuk membuat efek ticker. */
        @keyframes tickerScroll {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        /* Bentuk panel slider kandidat dengan latar blur, sudut membulat, bayangan, dan teks rata tengah. */
        .candidate-slider-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            padding: 18px;
            text-align: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        /* Atur ukuran gambar kandidat, cara pemotongan, bingkai, bayangan, dan transisi gambar. */
        .slider-img {
            width: 80px;
            height: 105px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid #fff;
            margin-bottom: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }

        /* Terapkan animasi kemunculan pada setiap slide kandidat. */
        .candidate-slide {
            animation: fadeInSlide 0.6s ease-in-out;
        }

        /* Munculkan slide dari sedikit lebih kecil dan transparan ke ukuran normal. */
        @keyframes fadeInSlide {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Susun kartu statistik menjadi dua kolom dengan jarak yang seragam. */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        /* Beri setiap statistik latar transparan, bingkai halus, jarak dalam, dan teks rata tengah. */
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px;
            border-radius: 14px;
            text-align: center;
            transition: transform 0.2s;
        }

        /* Naikkan kartu sedikit dan tingkatkan transparansi latar saat penunjuk berada di atasnya. */
        .stat-card:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.15);
        }

        /* Atur ukuran, ketebalan, dan jarak bawah angka pada kartu statistik. */
        .stat-card h4 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        /* Buat keterangan statistik lebih kecil dan sedikit transparan. */
        .stat-card span {
            font-size: 11px;
            opacity: 0.8;
        }

        /* Bentuk kartu login dengan latar putih transparan, bayangan, batas ukuran, dan sudut membulat. */
        .card-login { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(10px);
            padding: 35px 30px; 
            border-radius: 24px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); 
            width: 100%; 
            max-width: 400px; 
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        /* Tambahkan garis gradasi dekoratif di sisi atas kartu login. */
        .card-login::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, #059669, #0d9488, #0284c7);
        }

        /* Pusatkan area logo dan beri jarak dari elemen setelahnya. */
        .logo-container {
            text-align: center;
            margin-bottom: 15px;
        }

        /* Batasi lebar logo dan pertahankan proporsi gambar di dalam wadahnya. */
        .logo-sekolah {
            width: 70px;
            height: auto;
            object-fit: contain;
        }

        /* Pusatkan judul kartu login serta atur warna, ukuran, ketebalan, dan jaraknya. */
        .card-login h3 { 
            margin-bottom: 5px; 
            text-align: center; 
            color: #0f172a; 
            font-weight: 700;
            font-size: 20px;
        }

        /* Pusatkan subjudul sekolah dan gunakan warna serta ukuran teks yang lebih lembut. */
        .card-subtitle {
            text-align: center;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 20px;
        }

        /* Atur tampilan label formulir agar terbaca dan memiliki jarak dengan input. */
        .form-label {
            color: #334155;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        /* Beri ikon input latar, bingkai kiri, dan sudut membulat yang menyatu dengan kolom input. */
        .input-group-text {
            background-color: #f8fafc;
            border: 2px solid #e2e8f0;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #64748b;
        }

        /* Samakan ukuran, ruang dalam, batas, latar, dan efek transisi semua kontrol formulir. */
        .form-control, .form-select {
            padding: 11px 15px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            font-size: 13px;
            background-color: #f8fafc;
            transition: all 0.25s ease;
        }

        /* Hilangkan sisi batas yang bertumpuk dan sesuaikan sudut input di dalam grup input. */
        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
            background-color: #f8fafc;
        }

        /* Ubah warna latar dan batas serta tampilkan lingkaran fokus saat kontrol sedang dipilih. */
        .form-control:focus, .form-select:focus {
            background-color: #fff;
            border-color: #0d9488;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
        }

        /* Bentuk tombol utama dengan gradasi, teks putih, ruang dalam, dan bayangan berwarna. */
        .btn-primary-custom {
            background: linear-gradient(135deg, #059669, #0d9488);
            border: none;
            color: #fff;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 8px 20px -4px rgba(5, 150, 105, 0.4);
            transition: all 0.3s ease;
            margin-top: 5px;
        }

        /* Naikkan tombol saat diarahkan dan ubah bayangan serta gradasinya untuk umpan balik visual. */
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px -4px rgba(13, 148, 136, 0.5);
            background: linear-gradient(135deg, #047857, #0f766e);
        }

        /* Letakkan ikon tampil/sembunyi kata sandi di sisi kanan input secara vertikal di tengah. */
        .eye-icon { 
            position: absolute; 
            right: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            cursor: pointer; 
            width: 20px; 
            height: 20px; 
            color: #94a3b8;
            z-index: 10;
            transition: transform 0.2s ease, color 0.2s ease;
        }

        /* Ubah warna ikon mata ketika penunjuk berada di atasnya. */
        .eye-icon:hover { color: #0d9488; }
        /* Kecilkan ikon sesaat ketika ditekan sebagai umpan balik klik. */
        .eye-icon:active { transform: translateY(-50%) scale(0.9); }

        /* Beri kotak pesan kesalahan sudut membulat, ukuran teks kecil, dan warna latar/teks merah. */
        .alert {
            border-radius: 10px;
            font-size: 12px;
            border: none;
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>

<!-- Efek Partikel Cahaya Bergerak di Belakang -->
<!-- Wadah ini menempatkan elemen dekoratif di belakang konten halaman. -->
<ul class="bg-particles">
    <!-- Tiap elemen berikut menjadi satu partikel yang diatur oleh CSS. -->
    <li class="particle"></li>
    <li class="particle"></li>
    <li class="particle"></li>
    <li class="particle"></li>
    <li class="particle"></li>
    <li class="particle"></li>  
</ul>

<!-- Pembungkus utama yang mengatur posisi panel informasi dan kartu login. -->
<div class="login-wrapper">
    <!-- PANEL KIRI: LIVE, RUNNING TEXT, & SLIDER KANDIDAT -->
    <!-- Panel informasi disembunyikan pada layar kecil melalui kelas Bootstrap. -->
    <div class="interactive-panel d-none d-md-block">
        
        <!-- Judul utama yang mengajak pengguna berpartisipasi dalam pemilihan. -->
        <h1>Suara Kamu Penentu Masa Depan Sekolah!</h1>
        <!-- Penjelasan singkat tentang kandidat dan hak pilih pengguna. -->
        <p>Kenali para kandidat calon pemimpin terbaik SMK Negeri 2 Indramayu dan gunakan hak pilihmu.</p>

        <!-- Widget Pengumuman Teks Berjalan (Running Text) -->
        <!-- Kotak luar pengumuman yang mengatur posisi label dan teks ticker. -->
        <div class="announcement-box">
            <!-- Label ringkas untuk menandai bagian informasi. -->
            <span class="announcement-label">Info</span>
            <!-- Wadah pembatas area teks berjalan. -->
            <div class="ticker-container">
                <!-- Pesan informasi yang dianimasikan oleh CSS. -->
                <span class="ticker-text">Selamat datang di E-Voting OSIS SMK Negeri 2 Indramayu! Gunakan hak pilihmu dengan bijak, jujur, dan adil. Pastikan keluar akun setelah selesai memilih.</span>
            </div>
        </div>

        <!-- Kotak Slider Kandidat Berganti Otomatis -->
        <!-- Tampilkan slider hanya jika data kandidat tersedia. -->
        <?php if (!empty($array_kandidat)) : ?>
            <!-- Wadah visual untuk satu kandidat yang sedang ditampilkan. -->
            <div class="candidate-slider-box">
                <!-- Keterangan bahwa data di bawah adalah kandidat yang sudah terdaftar. -->
                <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; opacity: 0.8; font-weight: 600;">Kandidat Terdaftar</p>
                <!-- Elemen ini menjadi tempat kumpulan slide kandidat. -->
                <div id="slider-container">
                    <!-- Ulangi tampilan slide untuk setiap kandidat dan simpan nomor indeksnya. -->
                    <?php foreach ($array_kandidat as $index => $k) : ?>
                        <!-- Simpan indeks untuk JavaScript dan tampilkan hanya slide pertama pada awalnya. -->
                        <div class="candidate-slide" data-index="<?php echo $index; ?>" style="display: <?php echo ($index == 0) ? 'block' : 'none'; ?>;">
                            <?php 
                                // Ambil nama berkas foto dari data kandidat saat ini.
                                $foto_k = $k['foto'];
                                // Bentuk lokasi foto kandidat pada folder uploads utama.
                                $path_foto = 'uploads/' . $foto_k;
                                // Gunakan foto utama jika nama berkas terisi dan berkasnya ada.
                                if (!empty($foto_k) && file_exists($path_foto)) {
                                    // Simpan jalur foto utama untuk atribut sumber gambar.
                                    $img_src = $path_foto;
                                // Jika tidak ada di folder utama, periksa folder uploads milik admin.
                                } elseif (!empty($foto_k) && file_exists('admin/uploads/' . $foto_k)) {
                                    // Gunakan jalur foto dari folder admin jika berkas ditemukan.
                                    $img_src = 'admin/uploads/' . $foto_k;
                                } else {
                                    // Tandai bahwa foto tidak tersedia untuk kandidat ini.
                                    $img_src = '';
                                }
                            ?>

                            <!-- Tampilkan gambar kandidat jika salah satu jalur foto ditemukan. -->
                            <?php if ($img_src) : ?>
                                <!-- Cetak foto kandidat dengan teks alternatif dan kelas ukuran gambar. -->
                                <img src="<?php echo $img_src; ?>" alt="Kandidat" class="slider-img">
                            <?php else : ?>
                                <!-- Tampilkan placeholder sederhana jika kandidat belum memiliki foto. -->
                                <div class="slider-img" style="display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.2); font-size: 10px; color: #fff;">No Foto</div>
                            <?php endif; ?>

                            <!-- Tampilkan nomor urut kandidat dalam format dua digit. -->
                            <h5 style="font-size: 14px; font-weight: 700; margin-bottom: 2px;">No. Urut <?php echo sprintf('%02d', $k['nomor_urut']); ?></h5>
                            <!-- Tampilkan nama kandidat dengan pengamanan karakter HTML. -->
                            <p style="font-size: 12px; font-weight: 500; margin-bottom: 0; color: #f0fdf4;"><?php echo htmlspecialchars($k['nama_kandidat']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Grid Statistik Kehadiran Voting -->
        <!-- Susun dua ringkasan statistik secara berdampingan. -->
        <div class="stats-grid">
            <!-- Kartu pertama menunjukkan jumlah pemilih yang sudah menggunakan hak pilih. -->
            <div class="stat-card">
                <!-- Cetak jumlah suara dan jumlah total pemilih dengan pemisah garis miring. -->
                <h4><?php echo $jumlah_sudah_vote; ?> / <?php echo $jumlah_pemilih_terdaftar; ?></h4>
                <!-- Jelaskan arti angka statistik pertama. -->
                <span>Siswa Sudah Memilih</span>
            </div>
            <!-- Kartu kedua menunjukkan total kandidat yang datanya tersedia. -->
            <div class="stat-card">
                <!-- Hitung dan cetak jumlah elemen dalam array kandidat. -->
                <h4><?php echo count($array_kandidat); ?></h4>
                <!-- Jelaskan arti angka statistik kedua. -->
                <span>Kandidat Ketua OSIS</span>
            </div>
        </div>
    </div>

    <!-- PANEL KANAN: KARTU FORM LOGIN -->
    <!-- Kartu putih yang berisi identitas sekolah, pesan, dan formulir login. -->
    <div class="card-login">
        <!-- Wadah untuk memusatkan logo sekolah. -->
        <div class="logo-container">
            <!-- Muat berkas logo sekolah dan berikan teks alternatif jika gambar gagal dimuat. -->
            <img src="uploads/OIP.jpg" alt="Logo SMK" class="logo-sekolah">
        </div>

        <!-- Judul formulir login untuk pemilihan ketua OSIS. -->
        <h3>Pemilihan Voting Ketua OSIS</h3>
        <!-- Nama sekolah yang menyelenggarakan pemilihan. -->
        <p class="card-subtitle">SMK NEGERI 2 INDRAMAYU</p>

        <!-- Tampilkan pesan kesalahan hanya ketika variabel error berisi pesan. -->
        <?php if (!empty($error)) : ?>
            <!-- Tandai pesan sebagai peringatan dan pusatkan teksnya. -->
            <div class="alert py-2 text-center shadow-sm" role="alert">
                <!-- Cetak isi pesan kesalahan yang ditetapkan proses login. -->
                <?= $error; ?>
            </div>
        <?php endif; ?>

        <!-- Kirim data formulir menggunakan metode POST ke halaman ini sendiri. -->
        <form method="POST" action="">
            <!-- Kolom 1: memilih tipe akun yang akan login (admin atau pemilih) -->
            <!-- Kelompok input untuk memilih jenis akun. -->
            <div class="mb-3">
                <!-- Hubungkan label dengan pilihan jenis akun melalui atribut for/id. -->
                <label for="role" class="form-label">Login Sebagai</label>
                <!-- Kelompokkan ikon dan pilihan peran agar tampil sebagai satu kontrol. -->
                <div class="input-group">
                    <span class="input-group-text">
                        <!-- Ikon pengguna dalam format SVG untuk menghias pilihan jenis akun. -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </span>
                    <!-- Sediakan pilihan peran dan wajibkan pengguna memilih salah satunya. -->
                    <select name="role" id="role" class="form-select" required>
                        <!-- Pilihan untuk masuk sebagai pemilih dan menjadi pilihan awal. -->
                        <option value="pemilih">Pemilih</option>
                        <!-- Pilihan untuk masuk sebagai admin. -->
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <!-- Kolom 2: username atau nama pengguna yang digunakan untuk login -->
            <!-- Kelompok input untuk nama atau username akun. -->
            <div class="mb-3">
                <!-- Label yang menjelaskan kolom username. -->
                <label for="username" class="form-label">Nama / Username</label>
                <!-- Satukan ikon pengguna dengan kolom teks username. -->
                <div class="input-group">
                    <span class="input-group-text">
                        <!-- Ikon orang sebagai penanda visual untuk kolom username. -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </span>
                    <!-- Minta username berupa teks, wajib diisi, dan tampilkan petunjuk pengisian. -->
                    <input type="text" id="username" name="username" class="form-control" required autocomplete="off" placeholder="Masukkan nama kamu">
                </div>
            </div>

            <!-- Kolom 3: password akun yang digunakan untuk verifikasi login -->
            <!-- Kelompok input kata sandi dengan ruang bawah tambahan. -->
            <div class="mb-4">
                <!-- Label yang terhubung ke kolom kata sandi. -->
                <label for="password" class="form-label">Password</label>
                <!-- Wadah relatif agar ikon mata dapat ditempatkan di atas area input. -->
                <div class="position-relative">
                    <!-- Satukan ikon kunci dengan kolom kata sandi. -->
                    <div class="input-group">
                        <span class="input-group-text">
                            <!-- Ikon gembok untuk menandai input sebagai kata sandi. -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        </span>
                        <!-- Input wajib untuk kata sandi dengan ruang tambahan bagi ikon mata. -->
                        <input type="password" id="password" name="password" class="form-control pe-5" required placeholder="Masukkan password">
                    </div>
                    
                    <!-- Ikon mata tertutup; klik akan menjalankan fungsi untuk menampilkan kata sandi. -->
                    <svg id="eye-closed" onclick="togglePassword()" class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>

                    <!-- Ikon mata terbuka; disembunyikan sampai kata sandi ditampilkan. -->
                    <svg id="eye-open" onclick="togglePassword()" class="eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </div>
            </div>

            <!-- Tombol ini mengirim formulir dan menetapkan nama login untuk diproses PHP. -->
            <button type="submit" name="login" class="btn btn-primary-custom w-100 fw-bold">Masuk Sekarang</button>
        </form>
    </div>
</div>

<script>
// Toggle Password Visibility
// Fungsi ini dipanggil saat salah satu ikon mata diklik.
function togglePassword() {
    // Ambil elemen input kata sandi yang akan diubah tampilannya.
    var pass = document.getElementById("password");
    // Ambil ikon mata terbuka untuk ditampilkan saat kata sandi terlihat.
    var eyeOpen = document.getElementById("eye-open");
    // Ambil ikon mata tertutup untuk ditampilkan saat kata sandi tersembunyi.
    var eyeClosed = document.getElementById("eye-closed");

    // Periksa apakah kata sandi saat ini masih tersembunyi.
    if (pass.type === "password") {
        // Ubah tipe input menjadi teks agar karakter kata sandi terlihat.
        pass.type = "text";
        // Tampilkan ikon mata terbuka.
        eyeOpen.style.display = "block";
        // Sembunyikan ikon mata tertutup.
        eyeClosed.style.display = "none";
    } else {
        // Kembalikan tipe input agar karakter kata sandi tersamarkan.
        pass.type = "password";
        // Sembunyikan ikon mata terbuka.
        eyeOpen.style.display = "none";
        // Tampilkan kembali ikon mata tertutup.
        eyeClosed.style.display = "block";
    }
}

// Auto Slider Kandidat Bergerak di Panel Kiri
// Mulai dari slide pertama karena indeks array JavaScript dimulai dari nol.
let currentSlide = 0;
// Ambil semua elemen slide kandidat dari halaman.
const slides = document.querySelectorAll('.candidate-slide');

// Fungsi untuk menyembunyikan slide aktif dan menampilkan kandidat berikutnya.
function nextSlide() {
    // Hentikan fungsi jika tidak ada kandidat yang bisa ditampilkan.
    if (slides.length === 0) return;
    // Sembunyikan slide yang sedang terlihat.
    slides[currentSlide].style.display = 'none';
    // Pindah ke indeks berikutnya dan kembali ke awal setelah slide terakhir.
    currentSlide = (currentSlide + 1) % slides.length;
    // Tampilkan slide kandidat yang baru saja dipilih.
    slides[currentSlide].style.display = 'block';
}

// Jalankan pergantian otomatis hanya jika terdapat lebih dari satu kandidat.
if (slides.length > 1) {
    // Panggil fungsi pergantian slide setiap 3,5 detik.
    setInterval(nextSlide, 3500); // Ganti kandidat tiap 3.5 detik
}
</script>

</body>
</html>
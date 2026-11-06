<?php
/**
 * =====================================================================
 * 0. PENGATURAN KINERJA & ANTI TIMEOUT UNTUK DATA BESAR
 * =====================================================================
 */
set_time_limit(300); // Batas waktu kerja script dinaikkan jadi 5 menit
ini_set('memory_limit', '512M'); // Kapasitas memori ditingkatkan

/**
 * =====================================================================
 * 1. PENGATURAN SESI DAN KONTROL KEAMANAN
 * =====================================================================
 */
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../koneksi.php';

$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';

/**
 * =====================================================================
 * 1.1 PROSES UBAH STATUS VOTING
 * =====================================================================
 */
$file_status = 'status_voting.txt';
if (!file_exists($file_status)) {
    file_put_contents($file_status, '0');
}

if (isset($_GET['aksi']) && $_GET['aksi'] == 'ubah_status' && isset($_GET['status'])) {
    $statusBaru = intval($_GET['status']);
    file_put_contents($file_status, $statusBaru);
    header("Location: dashboard.php");
    exit();
}

/**
 * =====================================================================
 * 2. PROSES TAMBAH PEMILIH BARU
 * =====================================================================
 */
if (isset($_POST['tambah_pemilih'])) {
    // Ambil kredensial baru dari form dan cegah username yang sudah terdaftar.
    $username_baru = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password_baru = mysqli_real_escape_string($koneksi, $_POST['password']);

    $cek_user = mysqli_query($koneksi, "SELECT * FROM pemilih WHERE username = '$username_baru'");
    if (mysqli_num_rows($cek_user) > 0) {
        header("Location: dashboard.php");
        exit();
    }

    // Periksa apakah tabel pemilih memiliki kolom relasi admin sebelum menyimpan.
    $result_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM pemilih");
    $daftar_kolom_db = [];
    while($row_kolom = mysqli_fetch_assoc($result_kolom)){
        $daftar_kolom_db[] = $row_kolom['Field'];
    }

    // Isi id_admin jika kolom tersebut tersedia dan ada data admin.
    if (in_array('id_admin', $daftar_kolom_db)) {
        $q_first_admin = mysqli_query($koneksi, "SELECT id_admin FROM admin LIMIT 1");
        if ($q_first_admin && mysqli_num_rows($q_first_admin) > 0) {
            $d_fa = mysqli_fetch_assoc($q_first_admin);
            $fa_val = $d_fa['id_admin'];
            $query_tambah = mysqli_query($koneksi, "INSERT INTO pemilih (username, password, id_admin) VALUES ('$username_baru', '$password_baru', '$fa_val')");
        } else {
            $query_tambah = mysqli_query($koneksi, "INSERT INTO pemilih (username, password) VALUES ('$username_baru', '$password_baru')");
        }
    } else {
        $query_tambah = mysqli_query($koneksi, "INSERT INTO pemilih (username, password) VALUES ('$username_baru', '$password_baru')");
    }

    header("Location: dashboard.php");
    exit();
}

/**
 * =====================================================================
 * 2.1 PROSES IMPORT DATA PEMILIH DARI EXCEL/CSV (SUDAH DIOPTIMASI)
 * =====================================================================
 */
set_time_limit(0); // Import tidak dibatasi waktu eksekusi oleh aplikasi
ini_set('memory_limit', '512M'); // Memperbesar kapasitas memori PHP

if (isset($_POST['import_pemilih']) || (isset($_GET['aksi']) && $_GET['aksi'] === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    $jenis_import = 'error';
    $upload_error = isset($_FILES['file_csv']['error']) ? $_FILES['file_csv']['error'] : UPLOAD_ERR_NO_FILE;
    // Hentikan impor lebih awal jika unggahan gagal atau file terlalu besar.
    if ($upload_error !== UPLOAD_ERR_OK || empty($_FILES['file_csv']['tmp_name']) || !is_uploaded_file($_FILES['file_csv']['tmp_name'])) {
        $pesan_import = $upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran file melewati batas upload PHP. Naikkan upload_max_filesize dan post_max_size di php.ini.'
            : 'File gagal diunggah. Jika file berukuran besar, naikkan upload_max_filesize dan post_max_size di php.ini.';
    } else {
        $handle = fopen($_FILES['file_csv']['tmp_name'], 'r');
        $jumlah_sukses = 0;
        $jumlah_duplikat = 0;
        $jumlah_dilewati = 0;
        $jumlah_record = 0;
        $pesan_import = '';

        if ($handle === false) {
            $pesan_import = 'File CSV tidak dapat dibaca.';
        } else {
            // Pilih pemisah yang menghasilkan kolom terbanyak pada baris pertama.
            $delimiter = ',';
            $jumlah_kolom_terbaik = 0;
            foreach ([',', ';', "\t"] as $delimiter_kandidat) {
                rewind($handle);
                $baris_pertama = fgetcsv($handle, 0, $delimiter_kandidat);
                $jumlah_kolom = is_array($baris_pertama) ? count($baris_pertama) : 0;
                if ($jumlah_kolom > $jumlah_kolom_terbaik) {
                    $delimiter = $delimiter_kandidat;
                    $jumlah_kolom_terbaik = $jumlah_kolom;
                }
            }
            rewind($handle);

            // Periksa struktur tabel untuk mengetahui apakah relasi admin perlu diisi.
            $result_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM pemilih");
            $daftar_kolom_db = [];
            while ($row_kolom = mysqli_fetch_assoc($result_kolom)) {
                $daftar_kolom_db[] = $row_kolom['Field'];
            }

            $id_admin_val = null;
            if (in_array('id_admin', $daftar_kolom_db, true)) {
                $q_first_admin = mysqli_query($koneksi, "SELECT id_admin FROM admin LIMIT 1");
                if ($q_first_admin && mysqli_num_rows($q_first_admin) > 0) {
                    $d_fa = mysqli_fetch_assoc($q_first_admin);
                    $id_admin_val = $d_fa['id_admin'];
                }
            }

            // Siapkan satu query parametrik yang digunakan ulang untuk semua baris.
            if ($id_admin_val !== null) {
                $stmt_import = mysqli_prepare($koneksi, "INSERT IGNORE INTO pemilih (username, password, id_admin) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt_import, 'sss', $username_imp, $password_imp, $id_admin_val);
            } else {
                $stmt_import = mysqli_prepare($koneksi, "INSERT IGNORE INTO pemilih (username, password) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt_import, 'ss', $username_imp, $password_imp);
            }

            // Proses CSV per baris dalam transaksi agar data tersimpan konsisten.
            mysqli_begin_transaction($koneksi);
            try {
                while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                    // Lewati baris yang tidak memiliki dua kolom kredensial.
                    if (!is_array($data) || count($data) < 2) {
                        $jumlah_dilewati++;
                        continue;
                    }

                    $username_imp = trim($data[0]);
                    $password_imp = trim($data[1]);
                    if ($jumlah_record === 0) {
                        $username_imp = preg_replace('/^\xEF\xBB\xBF/', '', $username_imp);
                    }
                    $jumlah_record++;

                    // Abaikan baris judul kolom jika file CSV menyertakan header.
                    $username_header = strtolower($username_imp);
                    $password_header = strtolower($password_imp);
                    if (($username_header === 'username' || $username_header === 'nama' || $username_header === 'name' || $username_header === 'nis')
                        && ($password_header === 'password' || $password_header === 'nis' || $password_header === 'kata sandi')) {
                        continue;
                    }

                    if ($username_imp === '' || $password_imp === '') {
                        $jumlah_dilewati++;
                        continue;
                    }

                    // Simpan akun baru; hitung baris yang sudah ada sebagai duplikat.
                    mysqli_stmt_execute($stmt_import);
                    if (mysqli_stmt_affected_rows($stmt_import) > 0) {
                        $jumlah_sukses++;
                    } else {
                        $jumlah_duplikat++;
                    }
                }

                // Selesaikan transaksi dan siapkan pesan hasil impor untuk popup.
                mysqli_commit($koneksi);
                $jenis_import = 'success';
                $pesan_import = "Import selesai: $jumlah_sukses berhasil.";
            } catch (Throwable $e) {
                mysqli_rollback($koneksi);
                $pesan_import = 'Import dibatalkan karena terjadi kesalahan database. Periksa format dan panjang data di file CSV.';
            }

            mysqli_stmt_close($stmt_import);
            fclose($handle);
        }
    }

    // Simpan hasil sementara agar popup dapat muncul setelah halaman dialihkan.
    $_SESSION['import_notif'] = [
        'jenis' => $jenis_import,
        'pesan' => $pesan_import
    ];
    header('Location: dashboard.php');
    exit();
}

/**
 * =====================================================================
 * 3. PROSES HAPUS PEMILIH (SATUAN & MASAL)
 * =====================================================================
 */
if (isset($_GET['aksi']) && $_GET['aksi'] == 'hapus_pemilih') {
    // Hapus banyak akun sekaligus, tetapi pertahankan akun yang sudah memberikan suara.
    if (isset($_POST['ids']) && is_array($_POST['ids'])) {
        foreach ($_POST['ids'] as $id_pemilih) {
            $id_pemilih = mysqli_real_escape_string($koneksi, $id_pemilih);
            $cek_vote = mysqli_query($koneksi, "SELECT * FROM vote WHERE id_pemilih = '$id_pemilih'");
            if (mysqli_num_rows($cek_vote) == 0) {
                mysqli_query($koneksi, "DELETE FROM pemilih WHERE id_pemilih = '$id_pemilih'");
            }
        }
        echo json_encode(['status' => 'success']);
        exit();
    }
    
    // Hapus satu akun jika pemilih tersebut belum pernah memberikan suara.
    if (isset($_GET['id'])) {
        $id_pemilih = $_GET['id'];
        $cek_vote = mysqli_query($koneksi, "SELECT * FROM vote WHERE id_pemilih = '$id_pemilih'");
        if (mysqli_num_rows($cek_vote) == 0) {
            mysqli_query($koneksi, "DELETE FROM pemilih WHERE id_pemilih = '$id_pemilih'");
        }
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            exit();
        }
        header("Location: dashboard.php");
        exit();
    }
}

/**
 * =====================================================================
 * 4. PROSES EDIT PEMILIH
 * =====================================================================
 */
if (isset($_POST['update_pemilih'])) {
    // Ambil perubahan kredensial pemilih dari form edit.
    $id_p = $_POST['id_pemilih'];
    $username_baru = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password_baru = mysqli_real_escape_string($koneksi, $_POST['password']);

    // Deteksi kolom id_admin agar query sesuai dengan struktur database.
    $result_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM pemilih");
    $daftar_kolom_db = [];
    while($row_kolom = mysqli_fetch_assoc($result_kolom)){
        $daftar_kolom_db[] = $row_kolom['Field'];
    }

    if (in_array('id_admin', $daftar_kolom_db)) {
        $q_adm = mysqli_query($koneksi, "SELECT id_admin FROM pemilih WHERE id_pemilih = '$id_p'");
        $d_adm = mysqli_fetch_assoc($q_adm);
        if ($d_adm && !empty($d_adm['id_admin'])) {
            $id_admin_val = $d_adm['id_admin'];
            $update = mysqli_query($koneksi, "UPDATE pemilih SET username = '$username_baru', password = '$password_baru', id_admin = '$id_admin_val' WHERE id_pemilih = '$id_p'");
        } else {
            $q_first_admin = mysqli_query($koneksi, "SELECT id_admin FROM admin LIMIT 1");
            if ($q_first_admin && mysqli_num_rows($q_first_admin) > 0) {
                $d_fa = mysqli_fetch_assoc($q_first_admin);
                $fa_val = $d_fa['id_admin'];
                $update = mysqli_query($koneksi, "UPDATE pemilih SET username = '$username_baru', password = '$password_baru', id_admin = '$fa_val' WHERE id_pemilih = '$id_p'");
            } else {
                $update = mysqli_query($koneksi, "UPDATE pemilih SET username = '$username_baru', password = '$password_baru' WHERE id_pemilih = '$id_p'");
            }
        }
    } else {
        $update = mysqli_query($koneksi, "UPDATE pemilih SET username = '$username_baru', password = '$password_baru' WHERE id_pemilih = '$id_p'");
    }

    header("Location: dashboard.php");
    exit();
}

// Ambil kandidat dan jumlah suara untuk mengisi ringkasan dashboard.
$query = mysqli_query($koneksi, "SELECT * FROM kandidat ORDER BY nomor_urut ASC");

$q_total_suara = mysqli_query($koneksi, "SELECT COUNT(*) as total_keseluruhan FROM vote");
$d_total_suara = mysqli_fetch_assoc($q_total_suara);
$total_suara_keseluruhan = ($d_total_suara && $d_total_suara['total_keseluruhan'] > 0) ? $d_total_suara['total_keseluruhan'] : 0;

// Ambil semua pemilih atau saring berdasarkan kata pencarian admin.
if (!empty($search)) {
    $query_pemilih = mysqli_query($koneksi, "SELECT * FROM pemilih WHERE username LIKE '%$search%' OR id_pemilih LIKE '%$search%' ORDER BY id_pemilih DESC");
} else {
    $query_pemilih = mysqli_query($koneksi, "SELECT * FROM pemilih ORDER BY id_pemilih DESC");
}

// Hitung pemilih unik yang sudah voting jika tabel vote tersedia.
$total_sudah_vote = 0;
$q_vote = mysqli_query($koneksi, "SHOW TABLES LIKE 'vote'");
if (mysqli_num_rows($q_vote) > 0) {
    $q_sudah = mysqli_query($koneksi, "SELECT COUNT(DISTINCT id_pemilih) as total FROM vote");
    $d_sudah = mysqli_fetch_assoc($q_sudah);
    $total_sudah_vote = $d_sudah ? $d_sudah['total'] : 0;
}

$status_voting_aktif = intval(file_get_contents($file_status));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - E-Voting OSIS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Atur tipografi, latar, dan ukuran dasar dashboard. */
        * { box-sizing: border-box; font-family: 'Poppins', sans-serif; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, #065f46 0%, #0d9488 50%, #0284c7 100%); color: #1e293b; padding: 40px 20px; min-height: 100vh; }
        .container { max-width: 1000px; margin: 0 auto; }
        /* Gaya header, tombol aksi, dan kotak ringkasan dashboard. */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 24px 30px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15); border: 1px solid rgba(255, 255, 255, 0.8); }
        .header h2 { font-size: 22px; color: #0f172a; font-weight: 700; }
        .header-actions { display: flex; gap: 12px; align-items: center; }
        .btn-tambah { background: linear-gradient(135deg, #059669, #0d9488); color: #fff; padding: 10px 18px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); transition: all 0.2s ease; display: inline-block; border: none; cursor: pointer; }
        .btn-tambah:hover { transform: translateY(-1px); background: linear-gradient(135deg, #047857, #0f766e); color: #fff; }
        .btn-import { background: linear-gradient(135deg, #0284c7, #0ea5e9); color: #fff; padding: 10px 18px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3); transition: all 0.2s ease; display: inline-block; border: none; cursor: pointer; }
        .btn-import:hover { transform: translateY(-1px); background: linear-gradient(135deg, #0369a1, #0284c7); color: #fff; }
        .btn-logout { background: linear-gradient(135deg, #f87171, #ef4444); color: #fff; padding: 10px 18px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); transition: all 0.2s ease; }
        .btn-logout:hover { transform: translateY(-1px); background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
        .stat-box { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 20px 25px; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.8); display: flex; align-items: center; justify-content: space-between; }
        .stat-box h3 { font-size: 14px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-box .jumlah { font-size: 24px; font-weight: 700; color: #0d9488; }
        /* Tata letak tabel dan formulir pencarian data. */
        .table-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15); overflow-y: auto; max-height: 380px; border: 1px solid rgba(255, 255, 255, 0.8); margin-bottom: 35px; }
        .section-title { font-size: 18px; font-weight: 700; color: #ffffff; margin-bottom: 15px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; }
        .search-form { display: flex; gap: 10px; margin-bottom: 15px; }
        .search-form input { flex: 1; padding: 10px 14px; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; }
        .search-form input:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.15); }
        .btn-cari { background: #0d9488; color: white; border: none; padding: 0 20px; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .btn-reset { background: #cbd5e1; color: #333; padding: 0 15px; border-radius: 10px; text-decoration: none; display: flex; align-items: center; font-size: 13px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead { background-color: #f8fafc; position: sticky; top: 0; z-index: 10; }
        th { padding: 18px 20px; font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 2px solid #e2e8f0; background-color: #f8fafc; }
        td { padding: 18px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f8fafc; }
        .no-urut { font-size: 16px; font-weight: 700; color: #0d9488; text-align: center; }
        /* Tampilan identitas kandidat, hasil suara, dan status pemilih. */
        .foto-kandidat { width: 65px; height: 65px; border-radius: 12px; object-fit: cover; border: 2px solid #e2e8f0; }
        .no-foto { color: #94a3b8; font-size: 13px; font-style: italic; }
        .nama-kandidat { font-size: 15px; font-weight: 600; color: #0f172a; margin-bottom: 4px; }
        .suara-kandidat { font-size: 14px; font-weight: 600; color: #0284c7; background: #e0f2fe; padding: 6px 12px; border-radius: 20px; display: inline-block; }
        .visi-misi-admin { font-size: 12px; color: #475569; background: #f8fafc; padding: 8px 10px; border-radius: 8px; max-height: 80px; overflow-y: auto; border: 1px solid #e2e8f0; max-width: 250px; }
        .progress-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; position: relative; font-size: 11px; font-weight: 700; color: #0f172a; box-shadow: inset 0 0 0 4px #e2e8f0; }
        .progress-circle span { position: relative; z-index: 2; }
        .status-badge { font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; display: inline-block; margin-bottom: 6px; }
        .status-sudah { background: #dcfce7; color: #16a34a; }
        .status-belum { background: #ffedd5; color: #c2410c; }
        .btn-action { display: inline-block; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; margin-right: 6px; transition: all 0.2s ease; border: none; cursor: pointer; }
        .btn-edit { background-color: #fef3c7; color: #d97706; }
        .btn-edit:hover { background-color: #fde68a; }
        .btn-hapus { background-color: #fee2e2; color: #dc2626; }
        .btn-hapus:hover { background-color: #fca5a5; }
        .btn-hapus-masal { background: linear-gradient(135deg, #f87171, #ef4444); color: white; padding: 10px 18px; border-radius: 12px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); transition: 0.2s; }
        .btn-hapus-masal:hover { background: linear-gradient(135deg, #ef4444, #dc2626); }
        /* Kontrol buka/tutup voting, modal, dan elemen form. */
        .voting-control-box { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 20px 25px; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.8); display: flex; align-items: center; justify-content: space-between; }
        .voting-control-box h3 { font-size: 14px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-buka { background: #16a34a; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block; box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3); transition: 0.2s; }
        .btn-buka:hover { background: #15803d; }
        .btn-tutup { background: #dc2626; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3); transition: 0.2s; }
        .btn-tutup:hover { background: #b91c1c; }
        .modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); justify-content: center; align-items: center; }
        .modal-content { background: #ffffff; padding: 30px; border-radius: 20px; width: 100%; max-width: 450px; box-shadow: 0 20px 25px rgba(0,0,0,0.2); }
        .modal-content h3 { margin-bottom: 20px; font-size: 18px; color: #0f172a; font-weight: 700; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase; }
        .form-group input { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.15); }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn-simpan { background: #0d9488; color: white; padding: 10px 16px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; flex: 1; }
        .btn-simpan:hover { background: #0f766e; }
        .btn-batal { background: #e2e8f0; color: #475569; padding: 10px 16px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; flex: 1; text-align: center; }
        .btn-batal:hover { background: #cbd5e1; }
        .swal2-popup { border-radius: 20px !important; font-family: 'Poppins', sans-serif !important; }
        .swal2-styled.swal2-confirm { background-color: #0d9488 !important; border-radius: 10px !important; }
        .swal2-styled.swal2-confirm:hover { background-color: #0f766e !important; }
        input[type="checkbox"] { width: 18px; height: 18px; accent-color: #0d9488; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <!-- Header dashboard dan tombol keluar dari akun admin. -->
    <div class="header">
        <h2>Dashboard Admin</h2>
        <div class="header-actions">
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <!-- Ringkasan jumlah pemilih yang sudah memberikan suara. -->
    <div class="stat-box">
        <h3>Total Pemilih yang Sudah Voting</h3>
        <div class="jumlah"><?php echo $total_sudah_vote; ?> Orang</div>
    </div>

    <!-- Tampilkan status voting dan tombol untuk mengubahnya. -->
    <div class="voting-control-box">
        <div>
            <h3>Status Akses Voting Siswa</h3>
            <p style="font-size: 13px; color: #64748b; margin-top: 4px;">
                Kondisi saat ini: 
                <?php if ($status_voting_aktif == 1): ?>
                    <strong style="color: #16a34a;">BUKA (Siswa bisa memilih)</strong>
                <?php else: ?>
                    <strong style="color: #dc2626;">DITUTUP (Siswa tidak bisa memilih)</strong>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <?php if ($status_voting_aktif == 1): ?>
                <a href="dashboard.php?aksi=ubah_status&status=0" class="btn-tutup">Tutup Voting</a>
            <?php else: ?>
                <a href="dashboard.php?aksi=ubah_status&status=1" class="btn-buka">Buka Voting</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabel kandidat menampilkan profil, suara, persentase, dan aksi admin. -->
    <div class="section-title">
        <span>Daftar Calon Ketua OSIS</span>
        <a href="tambah.php" class="btn-tambah">+ Tambah Kandidat</a>
    </div>
    <div class="table-card" style="max-height: none; overflow-y: visible;">
        <table>
            <thead>
                <tr>
                    <th>No. Urut</th>
                    <th>Foto</th>
                    <th>Kandidat & Visi Misi</th>
                    <th>Perolehan Suara</th>
                    <th>Grafik Persentase</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($query)) : ?>
                <?php
                    // Hitung suara kandidat dan persentasenya dari total suara.
                    $id_kandidat = $row['id_kandidat'];
                    $q_suara = mysqli_query($koneksi, "SELECT COUNT(*) as total_suara FROM vote WHERE id_kandidat = '$id_kandidat'");
                    $d_suara = mysqli_fetch_assoc($q_suara);
                    $jumlah_suara = $d_suara ? $d_suara['total_suara'] : 0;
                    $persentase = ($total_suara_keseluruhan > 0) ? ($jumlah_suara / $total_suara_keseluruhan) * 100 : 0;
                ?>
                <tr>
                    <td class="no-urut"><?php echo sprintf('%02d', $row['nomor_urut']); ?></td>
                    <td>
                        <?php if (!empty($row['foto']) && file_exists('../uploads/' . $row['foto'])) : ?>
                            <img src="../uploads/<?php echo $row['foto']; ?>" class="foto-kandidat" alt="Foto">
                        <?php elseif (!empty($row['foto']) && file_exists('uploads/' . $row['foto'])) : ?>
                            <img src="uploads/<?php echo $row['foto']; ?>" class="foto-kandidat" alt="Foto">
                        <?php else : ?>
                            <span class="no-foto">Tanpa Foto</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="nama-kandidat"><?php echo $row['nama_kandidat']; ?></div>
                        <div class="visi-misi-admin">
                            <strong style="display: block; color: #0f172a; margin-bottom: 2px;">Visi & Misi:</strong>
                            <span><?php echo !empty($row['visi_misi']) ? nl2br($row['visi_misi']) : 'Belum ada visi & misi.'; ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="suara-kandidat"><?php echo $jumlah_suara; ?> Suara</span>
                    </td>
                    <td>
                        <div class="progress-circle" style="background: conic-gradient(#059669 <?php echo $persentase; ?>%, #f1f5f9 0deg);">
                            <span><?php echo number_format($persentase, 1); ?>%</span>
                        </div>
                    </td>
                    <td>
                        <a href="edit.php?id=<?php echo $row['id_kandidat']; ?>" class="btn-action btn-edit">Edit</a>
                        <button onclick="konfirmasiHapusKandidat('<?php echo $row['id_kandidat']; ?>')" class="btn-action btn-hapus">Hapus</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Bagian untuk mencari, mengimpor, menambah, dan mengelola pemilih. -->
    <div class="section-title">
        <span>Manajemen Data Pemilih (Siswa)</span>
        <div>
            <button onclick="hapusTerpilih()" class="btn-hapus-masal" style="margin-right: 5px;">🗑️ Hapus Terpilih</button>
            <button onclick="bukaModalImport()" class="btn-import" style="margin-right: 5px;">📂 Import Excel</button>
            <button onclick="bukaModalTambah()" class="btn-tambah">+ Tambah Pemilih</button>
        </div>
    </div>
    
    <!-- Kirim kata pencarian melalui URL agar daftar pemilih dapat disaring. -->
    <form action="dashboard.php" method="GET" class="search-form">
        <input type="text" name="search" placeholder="Cari berdasarkan Username / NIS..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn-cari">Cari</button>
        <?php if (!empty($search)): ?>
            <a href="dashboard.php" class="btn-reset">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" id="pilihSemua" onclick="togglePilihSemua(this)">
                    </th>
                    <th style="width: 60px; text-align: center;">No</th>
                    <th>NIS</th>
                    <th>Password</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (mysqli_num_rows($query_pemilih) > 0) {
                    $no = 1;
                    while ($p = mysqli_fetch_assoc($query_pemilih)) : 
                        $id_p = $p['id_pemilih'];
                        $username_p = isset($p['username']) ? $p['username'] : '-';
                        $password_p = isset($p['password']) ? $p['password'] : '-';

                        // Tentukan apakah pemilih sudah menggunakan hak pilihnya.
                        $cek_status = mysqli_query($koneksi, "SELECT * FROM vote WHERE id_pemilih = '$id_p'");
                        $sudah_vote = mysqli_num_rows($cek_status) > 0;
                ?>
                <tr data-id="<?php echo $id_p; ?>">
                    <td style="text-align: center;">
                        <input type="checkbox" name="pilih_pemilih" value="<?php echo $id_p; ?>" data-sudah-vote="<?php echo $sudah_vote ? '1' : '0'; ?>">
                    </td>
                    <td class="no-urut"><?php echo $no++; ?></td>
                    <td class="nama-kandidat"><?php echo htmlspecialchars($username_p); ?></td>
                    <td><?php echo htmlspecialchars($password_p); ?></td>
                    <td>
                        <?php if ($sudah_vote) : ?>
                            <span class="status-badge status-sudah">Sudah Memilih</span>
                        <?php else : ?>
                            <span class="status-badge status-belum">Belum Memilih</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button onclick="bukaModalEdit('<?php echo $p['id_pemilih']; ?>', '<?php echo addslashes($username_p); ?>', '<?php echo addslashes($password_p); ?>')" class="btn-action btn-edit">Edit</button>
                        
                        <?php if ($sudah_vote) : ?>
                            <button onclick="ingatkanSudahVote('<?php echo addslashes($username_p); ?>')" class="btn-action btn-hapus">Hapus</button>
                        <?php else : ?>
                            <button onclick="konfirmasiHapusPemilih(this, '<?php echo $p['id_pemilih']; ?>', '<?php echo addslashes($username_p); ?>')" class="btn-action btn-hapus">Hapus</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                    endwhile; 
                } else {
                    echo '<tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 30px;">Tidak ada data pemilih ditemukan.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal untuk memasukkan username/NIS dan password pemilih baru. -->
<div id="modalTambah" class="modal">
    <div class="modal-content">
        <h3>Tambah Data Pemilih Baru</h3>
        <form action="dashboard.php" method="POST">
            <div class="form-group">
                <label>Username / NIS</label>
                <input type="text" name="username" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="text" name="password" required autocomplete="off">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-batal" onclick="tutupModalTambah()">Batal</button>
                <button type="submit" name="tambah_pemilih" class="btn-simpan">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal untuk mengubah username/NIS dan password pemilih terpilih. -->
<div id="modalEdit" class="modal">
    <div class="modal-content">
        <h3>Edit Data Pemilih</h3>
        <form action="dashboard.php" method="POST">
            <input type="hidden" name="id_pemilih" id="edit_id_pemilih">
            <div class="form-group">
                <label>Username / NIS</label>
                <input type="text" name="username" id="edit_username" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="text" name="password" id="edit_password" required autocomplete="off">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-batal" onclick="tutupModalEdit()">Batal</button>
                <button type="submit" name="update_pemilih" class="btn-simpan">Perbarui</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal untuk memilih dan mengirim file CSV pemilih ke proses impor. -->
<div id="modalImport" class="modal">
    <div class="modal-content">
        <h3>Import Data Pemilih (CSV/Excel)</h3>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Format isi file baris pertama: <code>username,password</code></p>
        <form action="dashboard.php?aksi=import" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Pilih File CSV</label>
                <input type="file" name="file_csv" accept=".csv, .txt" required style="padding: 6px;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-batal" onclick="tutupModalImport()">Batal</button>
                <button type="submit" name="import_pemilih" class="btn-import" style="flex:1;">Upload & Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Tampilkan hasil impor satu kali setelah redirect kembali ke dashboard. -->
<?php if (isset($_SESSION['import_notif'])): ?>
    <?php $import_notif = $_SESSION['import_notif']; unset($_SESSION['import_notif']); ?>
    <script>
        Swal.fire({
            title: <?php echo json_encode($import_notif['jenis'] === 'success' ? 'Import Berhasil' : 'Import Gagal'); ?>,
            text: <?php echo json_encode($import_notif['pesan']); ?>,
            icon: <?php echo json_encode($import_notif['jenis']); ?>,
            confirmButtonText: 'Oke'
        });
    </script>
<?php endif; ?>

<script>
    // Buka atau tutup modal tambah pemilih.
    function bukaModalTambah() { document.getElementById('modalTambah').style.display = 'flex'; }
    function tutupModalTambah() { document.getElementById('modalTambah').style.display = 'none'; }

    // Isi kolom modal edit dengan data pemilih yang dipilih admin.
    function bukaModalEdit(id, username, password) {
        document.getElementById('edit_id_pemilih').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_password').value = password;
        document.getElementById('modalEdit').style.display = 'flex';
    }
    function tutupModalEdit() { document.getElementById('modalEdit').style.display = 'none'; }

    // Buka atau tutup modal pemilihan file impor.
    function bukaModalImport() { document.getElementById('modalImport').style.display = 'flex'; }
    function tutupModalImport() { document.getElementById('modalImport').style.display = 'none'; }

    // Tutup modal ketika admin mengeklik area di luar isi modal.
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

    // Centang atau hapus centang semua pemilih yang terlihat di tabel.
    function togglePilihSemua(source) {
        let checkboxes = document.querySelectorAll('input[name="pilih_pemilih"]');
        checkboxes.forEach((cb) => { cb.checked = source.checked; });
    }

    // Minta konfirmasi sebelum membuka proses hapus kandidat.
    function konfirmasiHapusKandidat(id) {
        Swal.fire({
            title: 'Hapus Kandidat?',
            text: "Data kandidat dan perolehan suaranya akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'hapus_kandidat.php?id=' + id;
            }
        });
    }

    // Konfirmasi dan hapus satu pemilih tanpa memuat ulang seluruh halaman.
    function konfirmasiHapusPemilih(buttonElement, idPemilih, username) {
        Swal.fire({
            title: 'Hapus Siswa?',
            text: "Apakah Anda yakin ingin menghapus pemilih '" + username + "'?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('dashboard.php?aksi=hapus_pemilih&id=' + idPemilih, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(response => {
                    if (response.ok) {
                        let row = buttonElement.closest('tr');
                        row.style.transition = '0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                        Swal.fire({
                            title: 'Terhapus!',
                            text: 'Data pemilih berhasil dihapus.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Gagal!', 'Terjadi kesalahan saat menghapus data.', 'error');
                    }
                }).catch(error => {
                    Swal.fire('Gagal!', 'Koneksi jaringan bermasalah.', 'error');
                });
            }
        });
    }

    // Beri tahu admin jika pemilih yang dipilih sudah melakukan voting.
    function ingatkanSudahVote(username) {
        Swal.fire({
            title: 'Tidak Dapat Dihapus!',
            text: "Siswa '" + username + "' sudah menggunakan hak pilihnya, jadi data tidak bisa dihapus demi keamanan suara.",
            icon: 'warning',
            confirmButtonText: 'Mengerti'
        });
    }

    // Validasi pilihan admin, lalu hapus semua pemilih terpilih lewat request AJAX.
    function hapusTerpilih() {
        let selectedCheckboxes = document.querySelectorAll('input[name="pilih_pemilih"]:checked');
        
        if (selectedCheckboxes.length === 0) {
            Swal.fire('Belum Ada yang Dipilih', 'Silakan centang minimal satu data pemilih yang ingin dihapus.', 'info');
            return;
        }

        let ids = [];
        let adaYangSudahVote = false;

        selectedCheckboxes.forEach((cb) => {
            if (cb.getAttribute('data-sudah-vote') === '1') {
                adaYangSudahVote = true;
            }
            ids.push(cb.value);
        });

        if (adaYangSudahVote) {
            Swal.fire('Perhatian!', 'Beberapa data yang Anda pilih sudah melakukan voting dan tidak dapat dihapus.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Hapus ' + ids.length + ' Data Terpilih?',
            text: "Data yang dipilih akan dihapus secara massal.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus Semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                let formData = new URLSearchParams();
                ids.forEach(id => formData.append('ids[]', id));

                fetch('dashboard.php?aksi=hapus_pemilih', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        selectedCheckboxes.forEach(cb => {
                            let row = cb.closest('tr');
                            row.style.transition = '0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 300);
                        });
                        document.getElementById('pilihSemua').checked = false;
                        Swal.fire({
                            title: 'Berhasil!',
                            text: 'Data terpilih berhasil dihapus.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Gagal!', 'Terjadi kesalahan sistem.', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Gagal!', 'Terjadi kesalahan koneksi.', 'error');
                });
            }
        });
    }
</script>

</body>
</html>
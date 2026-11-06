<?php
// ==========================================
// 1. PENGATURAN AWAL DAN KONEKSI DATABASE
// ==========================================
ini_set('display_errors', 1);
error_reporting(E_ALL);
include '../koneksi.php';

$error_msg = '';

// Mengambil ID kandidat dari URL (parameter GET)
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'];

// Mengambil data kandidat berdasarkan ID yang dipilih (DISESUAIKAN MENJADI id_kandidat)
$query = mysqli_query($koneksi, "SELECT * FROM kandidat WHERE id_kandidat = '$id'");
$data = mysqli_fetch_assoc($query);

if (!$data) {
    header("Location: dashboard.php");
    exit();
}

// ==========================================
// 2. PROSES KETIKA FORM EDIT DISUBMIT
// ==========================================
if (isset($_POST['submit'])) {
    // Ambil data form dan bersihkan teks sebelum menyusun query database.
    $nomor_urut    = $_POST['nomor_urut'];
    $nama_kandidat = mysqli_real_escape_string($koneksi, trim($_POST['nama_kandidat']));
    $visi_misi     = mysqli_real_escape_string($koneksi, trim($_POST['visi_misi']));
    
    $foto          = $_FILES['foto']['name'];
    $tmp_name      = $_FILES['foto']['tmp_name'];

    // Jika foto baru dipilih, siapkan nama file unik dan pastikan folder tersedia.
    if (!empty($foto)) {
        $foto_baru = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $foto);
        
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }

        // Pindahkan foto baru ke folder uploads sebelum memperbarui data kandidat.
        if (move_uploaded_file($tmp_name, 'uploads/' . $foto_baru)) {
            // Hapus foto lama jika ada
            if (!empty($data['foto']) && file_exists('uploads/' . $data['foto'])) {
                unlink('uploads/' . $data['foto']);
            }

            // Update data beserta foto baru (DISESUAIKAN MENJADI id_kandidat)
            $sql = "UPDATE kandidat SET nomor_urut = '$nomor_urut', nama_kandidat = '$nama_kandidat', visi_misi = '$visi_misi', foto = '$foto_baru' WHERE id_kandidat = '$id'";
        } else {
            $error_msg = "Gagal mengunggah foto baru.";
        }
    } else {
        // Jika admin tidak mengganti foto, update data teks saja (DISESUAIKAN MENJADI id_kandidat)
        $sql = "UPDATE kandidat SET nomor_urut = '$nomor_urut', nama_kandidat = '$nama_kandidat', visi_misi = '$visi_misi' WHERE id_kandidat = '$id'";
    }

    // Simpan perubahan jika proses upload foto tidak menghasilkan error.
    if (empty($error_msg)) {
        if (mysqli_query($koneksi, $sql)) {
            header("Location: dashboard.php");
            exit();
        } else {
            $error_msg = "Error Database: " . mysqli_error($koneksi);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kandidat Ketua OSIS - Premium</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* Atur gaya dasar font dan ukuran elemen di halaman edit. */
        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Pusatkan kartu form di atas latar halaman. */
        body {
            background: linear-gradient(135deg, #065f46 0%, #0d9488 50%, #0284c7 100%);
            color: #333;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Kartu utama yang membungkus form edit kandidat. */
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

        .card-custom::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #059669, #0d9488, #0284c7);
        }

        /* Ikon dan teks judul form. */
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

        /* Label, input, dan fokus visual pada kolom form. */
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

        /* Kotak pratinjau foto agar gambar tampil dengan ukuran tetap. */
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
        }

        /* Tombol simpan/batal dan pesan jika proses edit gagal. */
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

        .btn-batal {
            background-color: #f1f5f9;
            color: #475569;
            border: 2px solid #e2e8f0;
        }

        .btn-batal:hover {
            background-color: #e2e8f0;
            color: #1e293b;
        }

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
    <!-- Ikon dekoratif untuk menandai halaman pengeditan kandidat. -->
    <div class="form-badge">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
        </svg>
    </div>

    <h2>Edit Kandidat</h2>
    <p class="card-subtitle">Perbarui data kandidat ketua osis</p>

    <?php if (!empty($error_msg)) : ?>
        <div class="alert-error"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <!-- Form mengirim data teks dan file foto ke halaman ini melalui POST. -->
    <form action="" method="POST" enctype="multipart/form-data">
        
        <!-- Kolom untuk mengubah nomor urut kandidat. -->
        <div class="mb-3">
            <label for="nomor_urut" class="form-label">Nomor Urut</label>
            <div class="input-group">
                <span class="input-group-text">#</span>
                <input type="number" id="nomor_urut" name="nomor_urut" class="form-control" value="<?php echo htmlspecialchars($data['nomor_urut']); ?>" required>
            </div>
        </div>

        <!-- Kolom untuk mengubah nama kandidat. -->
        <div class="mb-3">
            <label for="nama_kandidat" class="form-label">Nama Kandidat</label>
            <div class="input-group">
                <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </span>
                <input type="text" id="nama_kandidat" name="nama_kandidat" class="form-control" value="<?php echo htmlspecialchars($data['nama_kandidat']); ?>" required autocomplete="off">
            </div>
        </div>

        <!-- Kolom untuk mengubah visi dan misi kandidat. -->
        <div class="mb-3">
            <label for="visi_misi" class="form-label">Visi & Misi</label>
            <textarea id="visi_misi" name="visi_misi" class="form-control" rows="4" required style="border-radius: 12px;"><?php echo htmlspecialchars($data['visi_misi']); ?></textarea>
        </div>

        <!-- Tampilkan foto tersimpan saat ini atau keterangan jika belum ada. -->
        <!-- Pemilihan foto baru bersifat opsional; preview diperbarui di browser. -->
        <div class="mb-3">
            <label class="form-label">Foto Kandidat</label>
            <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Foto Saat Ini:</div>
            <div class="preview-container">
                <?php if (!empty($data['foto']) && file_exists('uploads/' . $data['foto'])): ?>
                    <img id="imgPreview" src="uploads/<?php echo htmlspecialchars($data['foto']); ?>" alt="Foto Kandidat">
                <?php else: ?>
                    <img id="imgPreview" src="#" alt="Preview" style="display:none;">
                    <span id="previewText" style="font-size: 11px; color: #94a3b8;">Tidak Ada Foto</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="foto" class="form-label">Ganti Foto (Opsional)</label>
            <input type="file" id="foto" name="foto" class="form-control" accept="image/*" onchange="previewImage(event)">
        </div>

        <!-- Tombol untuk membatalkan perubahan atau menyimpannya ke database. -->
        <div class="btn-group-custom">
            <a href="dashboard.php" class="btn btn-batal">Batal</a>
            <button type="submit" name="submit" class="btn btn-simpan">Update Kandidat</button>
        </div>
    </form>
</div>

<script>
    // Baca foto yang dipilih dan tampilkan sebagai pratinjau sebelum disimpan.
    function previewImage(event) {
        const reader = new FileReader();
        const imgPreview = document.getElementById('imgPreview');
        
        reader.onload = function() {
            imgPreview.src = reader.result;
            imgPreview.style.display = 'block';
            const previewText = document.getElementById('previewText');
            if(previewText) previewText.style.display = 'none';
        }
        
        if(event.target.files[0]) {
            reader.readAsDataURL(event.target.files[0]);
        }
    }
</script>

</body>
</html>
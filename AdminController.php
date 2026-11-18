<?php
class AdminController {
    private $db;
    private $model;

    public function __construct($db) {
        $this->db = $db;
        $this->model = new AlatModel($db);
      
    }

    public function dashboard() {
        // Cek Session
        if ($_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }

        $total_user = (int) $this->db->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
        $total_alat = (int) $this->db->query("SELECT COUNT(*) AS total FROM alat")->fetch_assoc()['total'];
        $logs = $this->db->query("SELECT * FROM log_aktivitas ORDER BY tanggal DESC LIMIT 10");

        include 'views/admin/dashboard.php';
    }

   
    public function kelolaAlat() {
        $data_alat = $this->model->getAllAlat();
        include 'views/alat/index.php';
    }

    public function simpanUser() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $this->validasiCsrf();
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Enkripsi password
        $role = $_POST['role'];

        // Query untuk memasukkan data baru
        $stmt = $this->db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $password, $role);

        if ($stmt->execute()) {
            // Catat ke Log Aktivitas
            $this->simpanLog("Tambah User", "Admin menambah user baru: $username dengan role: $role");
            
            header("Location: index.php?page=kelola-user&msg=tambah_sukses");
            exit();
        } else {
            die("Gagal menambah user: " . $this->db->error);
        }
    }
}

   
    

   

    public function updateUser() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $this->validasiCsrf();
        $id = intval($_POST['id']);
        $username = $_POST['username'];
        $role = $_POST['role'];

        // Cek apakah password diisi (ingin ganti password)
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $password, $role, $id);
        } else {
            // Jika password kosong, jangan update kolom password
            $stmt = $this->db->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssi", $username, $role, $id);
        }

        if ($stmt->execute()) {
            // Catat ke Log Aktivitas
            $this->simpanLog("Update User", "Admin mengubah data user: $username (ID: $id)");
            header("Location: index.php?page=kelola-user&msg=update_sukses");
            exit();
        } else {
            die("Gagal update: " . $this->db->error);
        }
    }
}

 
public function hapusUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method tidak diizinkan.'); }
    $this->validasiCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) { header("Location: index.php?page=kelola-user&msg=id_invalid"); exit; }
    $stmt = $this->db->prepare("DELETE FROM users WHERE id = ? AND role <> 'admin'");
    $stmt->bind_param('i', $id);
    try {
        $stmt->execute(); $deleted = $stmt->affected_rows; $stmt->close();
        header("Location: index.php?page=kelola-user&msg=" . ($deleted ? "hapus_berhasil" : "hapus_gagal")); exit;
    } catch (Throwable $e) {
        $stmt->close();
        header("Location: index.php?page=kelola-user&msg=hapus_gagal"); exit;
    }
}

    public function indexUser() {
    $query = "SELECT * FROM users WHERE role != 'admin' ORDER BY id DESC";
    // PERBAIKAN: Ganti $this->conn menjadi $this->db
    $users = $this->db->query($query); 
    require 'views/admin/kelola_user.php';
}
// --- MANAJEMEN ALAT ---

public function indexAlat() {
    $alat = $this->db->query("SELECT * FROM alat ORDER BY id DESC");
    require 'views/admin/kelola_alat.php';
}

private function validasiCsrf(): void {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Permintaan tidak valid (CSRF). Muat ulang halaman lalu coba lagi.');
    }
}

private function uploadFoto(): ?string {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload foto gagal.');
    if ((int)$_FILES['foto']['size'] > 5 * 1024 * 1024) throw new RuntimeException('Ukuran foto maksimal 5 MB.');
    $tmp = $_FILES['foto']['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || @getimagesize($tmp) === false) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WEBP yang valid.');
    }
    $dir = __DIR__ . '/../assets/img/alat/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder penyimpanan foto tidak tersedia.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $dir . $filename)) throw new RuntimeException('Foto gagal disimpan.');
    return $filename;
}

public function simpanAlat() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method tidak diizinkan.'); }
    $this->validasiCsrf();
    $nama_alat = trim((string)($_POST['nama_alat'] ?? ''));
    $stok = filter_var($_POST['stok'] ?? null, FILTER_VALIDATE_INT);
    if ($nama_alat === '' || strlen($nama_alat) > 600 || $stok === false || $stok < 0) {
        header("Location: index.php?page=kelola-alat&status=input_gagal"); exit;
    }
    $foto = 'default.jpg';
    try {
        $uploaded = $this->uploadFoto();
        if ($uploaded !== null) $foto = $uploaded;
        $status = $stok > 0 ? 'tersedia' : 'kosong';
        $stmt = $this->db->prepare("INSERT INTO alat (nama_alat, foto, stok, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $nama_alat, $foto, $stok, $status);
        $stmt->execute(); $stmt->close();
        $this->simpanLog("Tambah Alat", "Admin menambahkan alat: {$nama_alat}");
        header("Location: index.php?page=kelola-alat&status=created"); exit;
    } catch (Throwable $e) {
        if ($foto !== 'default.jpg') @unlink(__DIR__ . '/../assets/img/alat/' . $foto);
        header("Location: index.php?page=kelola-alat&status=upload_gagal"); exit;
    }
}

public function updateAlat() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method tidak diizinkan.'); }
    $this->validasiCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $nama_alat = trim((string)($_POST['nama_alat'] ?? ''));
    $stok = filter_var($_POST['stok'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1 || $nama_alat === '' || strlen($nama_alat) > 600 || $stok === false || $stok < 0) {
        header("Location: index.php?page=kelola-alat&status=input_gagal"); exit;
    }
    $get = $this->db->prepare("SELECT foto FROM alat WHERE id=?");
    $get->bind_param('i', $id); $get->execute(); $old = $get->get_result()->fetch_assoc(); $get->close();
    if (!$old) { header("Location: index.php?page=kelola-alat&status=not_found"); exit; }
    $foto = $old['foto'];
    try {
        $uploaded = $this->uploadFoto();
        if ($uploaded !== null) $foto = $uploaded;
        $status = $stok > 0 ? 'tersedia' : 'kosong';
        $stmt = $this->db->prepare("UPDATE alat SET nama_alat=?, foto=?, stok=?, status=? WHERE id=?");
        $stmt->bind_param("ssisi", $nama_alat, $foto, $stok, $status, $id);
        $stmt->execute(); $stmt->close();
        if ($uploaded !== null && $old['foto'] !== 'default.jpg') {
            $oldPath = __DIR__ . '/../assets/img/alat/' . basename($old['foto']);
            if (is_file($oldPath)) @unlink($oldPath);
        }
        $this->simpanLog("Update Alat", "Admin mengubah data alat: {$nama_alat} (ID: {$id})");
        header("Location: index.php?page=kelola-alat&status=updated"); exit;
    } catch (Throwable $e) {
        if (isset($uploaded) && $uploaded !== null) @unlink(__DIR__ . '/../assets/img/alat/' . $uploaded);
        header("Location: index.php?page=kelola-alat&status=update_gagal"); exit;
    }
}

public function hapusAlat() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method tidak diizinkan.'); }
    $this->validasiCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) { header("Location: index.php?page=kelola-alat&status=id_invalid"); exit; }
    $stmt = $this->db->prepare("DELETE FROM alat WHERE id=?");
    $stmt->bind_param('i', $id);
    try {
        $stmt->execute(); $deleted = $stmt->affected_rows; $stmt->close();
        header("Location: index.php?page=kelola-alat&status=" . ($deleted ? "deleted" : "not_found")); exit;
    } catch (Throwable $e) {
        $stmt->close();
        header("Location: index.php?page=kelola-alat&status=hapus_gagal"); exit;
    }
}

private function simpanLog($aksi, $keterangan) {
    // Ambil user_id dari session, jika tidak ada (belum login/gagal login) set jadi null
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Gunakan query yang mengizinkan NULL pada user_id
    $stmt = $this->db->prepare("INSERT INTO log_aktivitas (user_id, aksi, keterangan) VALUES (?, ?, ?)");
    
    // "i" untuk integer, "s" untuk string. Jika null, kirimkan null.
    $stmt->bind_param("iss", $user_id, $aksi, $keterangan);
    $stmt->execute();
}

// Fungsi untuk menampilkan halaman Log
public function indexLog() {
    $query = "SELECT l.*, u.username FROM log_aktivitas l 
              LEFT JOIN users u ON l.user_id = u.id 
              ORDER BY l.tanggal DESC";
    $logs = $this->db->query($query);
    require 'views/admin/log_aktivitas.php';
}

    
   
}
?>
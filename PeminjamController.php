<?php
require_once 'models/AlatModel.php';

class PeminjamController {
    private $db;
    private $alatModel;

    public function __construct($db) {
        $this->db = $db;
        $this->alatModel = new AlatModel($db);
    }

    private function simpanLog($aksi, $keterangan) {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $stmt = $this->db->prepare("INSERT INTO log_aktivitas (user_id, aksi, keterangan) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $userId, $aksi, $keterangan);
        $stmt->execute();
        $stmt->close();
    }

    public function index() {
        $result = $this->db->query("SELECT id, nama_alat, stok, status, foto FROM alat ORDER BY nama_alat ASC");
        $daftarAlat = [];
        while ($row = $result->fetch_assoc()) {
            $daftarAlat[] = $row;
        }
        require 'views/peminjam/dashboard.php';
    }

    public function formAjukan() {
        $pilihan = $_POST['alat'] ?? [];
        if (!is_array($pilihan) || empty($pilihan)) {
            header('Location: index.php?page=dashboard&pesan=pilih_barang');
            exit;
        }
        $ids = [];
        foreach ($pilihan as $id => $jumlah) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            $jumlah = filter_var($jumlah, FILTER_VALIDATE_INT);
            if ($id && $id > 0 && $jumlah !== false && $jumlah > 0) $ids[$id] = $jumlah;
        }
        if (!$ids) {
            header('Location: index.php?page=dashboard&pesan=jumlah_tidak_valid');
            exit;
        }
        $daftarPilihan = [];
        $stmt = $this->db->prepare('SELECT id, nama_alat, stok FROM alat WHERE id = ?');
        foreach ($ids as $id => $jumlah) {
            $stmt->bind_param('i', $id); $stmt->execute();
            $alat = $stmt->get_result()->fetch_assoc();
            if (!$alat || (int)$alat['stok'] < $jumlah) {
                $stmt->close();
                header('Location: index.php?page=dashboard&pesan=gagal_stok'); exit;
            }
            $alat['jumlah'] = $jumlah;
            $daftarPilihan[] = $alat;
        }
        $stmt->close();
        require 'views/peminjam/ajukan.php';
    }

    /** Menampilkan struk digital untuk satu transaksi peminjaman. */
    public function struk() {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $kode = trim((string) ($_GET['kode'] ?? ''));
        if ($userId < 1 || $kode === '' || !preg_match('/^[A-Z0-9-]{6,40}$/', $kode)) {
            http_response_code(400);
            echo 'Nomor transaksi tidak valid.';
            return;
        }

        $stmt = $this->db->prepare("SELECT p.id, p.kode_transaksi, p.jumlah, p.tanggal_pinjam,
                p.tanggal_kembali_seharusnya, p.status_pinjam, p.denda,
                a.nama_alat, u.username
            FROM peminjaman p
            JOIN alat a ON a.id = p.alat_id
            JOIN users u ON u.id = p.user_id
            WHERE p.kode_transaksi = ? AND p.user_id = ?
            ORDER BY p.id ASC");
        $stmt->bind_param('si', $kode, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $stmt->close();

        if (!$items) {
            http_response_code(404);
            echo 'Struk tidak ditemukan atau bukan milik akun ini.';
            return;
        }
        require 'views/peminjam/struk.php';
    }

    public function simpanPengajuan() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php?page=dashboard'); exit; }
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $pilihan = $_POST['alat'] ?? [];
        $tglPinjam = trim((string) ($_POST['tanggal_pinjam'] ?? ''));
        $tglSeharusnya = trim((string) ($_POST['tanggal_kembali_seharusnya'] ?? ''));
        $pinjamDate = DateTime::createFromFormat('!Y-m-d', $tglPinjam);
        $dueDate = DateTime::createFromFormat('!Y-m-d', $tglSeharusnya);
        if ($userId < 1 || !is_array($pilihan) || !$pilihan || !$pinjamDate || !$dueDate || $pinjamDate->format('Y-m-d') !== $tglPinjam || $dueDate->format('Y-m-d') !== $tglSeharusnya || $dueDate < $pinjamDate) {
            header('Location: index.php?page=dashboard&pesan=data_tanggal_tidak_valid'); exit;
        }
        $items = [];
        foreach ($pilihan as $id => $jumlah) {
            $id = filter_var($id, FILTER_VALIDATE_INT); $jumlah = filter_var($jumlah, FILTER_VALIDATE_INT);
            if ($id && $id > 0 && $jumlah !== false && $jumlah > 0) $items[$id] = $jumlah;
        }
        if (!$items) { header('Location: index.php?page=dashboard&pesan=jumlah_tidak_valid'); exit; }
        try {
            $this->db->begin_transaction();
            $cek = $this->db->prepare('SELECT nama_alat, stok FROM alat WHERE id = ? FOR UPDATE');
            $kodeTransaksi = 'TRX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $insert = $this->db->prepare("INSERT INTO peminjaman (user_id, alat_id, jumlah, kode_transaksi, tanggal_pinjam, tanggal_kembali_seharusnya, status_pinjam, denda, stok_dikurangi) VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, 0)");
            $ringkasan = [];
            foreach ($items as $alatId => $jumlah) {
                $cek->bind_param('i', $alatId); $cek->execute(); $alat = $cek->get_result()->fetch_assoc();
                if (!$alat || (int)$alat['stok'] < $jumlah) throw new RuntimeException('stok_habis');
                $insert->bind_param('iiisss', $userId, $alatId, $jumlah, $kodeTransaksi, $tglPinjam, $tglSeharusnya); $insert->execute();
                $ringkasan[] = $alat['nama_alat'] . ' x' . $jumlah;
            }
            $cek->close(); $insert->close();
            $detail = implode(', ', $ringkasan);
            $this->simpanLog('Ajukan Peminjaman', "Peminjam mengajukan beberapa alat: {$detail} untuk tanggal: {$tglPinjam}");
            $this->db->commit();
            header('Location: index.php?page=struk&kode=' . urlencode($kodeTransaksi)); exit;
        } catch (Throwable $e) {
            try { $this->db->rollback(); } catch (Throwable $ignored) {}
            $message = $e->getMessage() === 'stok_habis' ? 'gagal_stok' : 'gagal_sistem';
            header('Location: index.php?page=dashboard&pesan=' . $message); exit;
        }
    }

    /** Peminjam meminta pengembalian; stok baru bertambah setelah petugas menerima barang. */
    public function ajukanPengembalian() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=dashboard');
            exit;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $loanId = filter_input(INPUT_POST, 'id_peminjaman', FILTER_VALIDATE_INT);
        if ($userId < 1 || !$loanId || $loanId < 1) {
            header('Location: index.php?page=dashboard&pesan=pengembalian_gagal');
            exit;
        }

        try {
            $this->db->begin_transaction();
            $stmt = $this->db->prepare("SELECT p.status_pinjam, a.nama_alat FROM peminjaman p JOIN alat a ON a.id = p.alat_id WHERE p.id = ? AND p.user_id = ? FOR UPDATE");
            $stmt->bind_param('ii', $loanId, $userId);
            $stmt->execute();
            $loan = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$loan || $loan['status_pinjam'] !== 'disetujui') {
                throw new RuntimeException('Peminjaman tidak aktif atau bukan milik akun ini.');
            }

            $update = $this->db->prepare("UPDATE peminjaman SET status_pinjam = 'menunggu_pengembalian' WHERE id = ? AND user_id = ? AND status_pinjam = 'disetujui'");
            $update->bind_param('ii', $loanId, $userId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                $update->close();
                throw new RuntimeException('Status peminjaman sudah berubah.');
            }
            $update->close();

            $namaAlat = $loan['nama_alat'];
            $this->simpanLog('Ajukan Pengembalian', "Peminjam mengajukan pengembalian {$namaAlat} pada peminjaman ID: {$loanId}.");
            $this->db->commit();
            header('Location: index.php?page=dashboard&pesan=pengembalian_diajukan');
            exit;
        } catch (Throwable $e) {
            try { $this->db->rollback(); } catch (Throwable $ignored) {}
            header('Location: index.php?page=dashboard&pesan=pengembalian_gagal');
            exit;
        }
    }
}

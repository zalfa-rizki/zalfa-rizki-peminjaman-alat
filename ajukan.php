<?php
$pageTitle='Konfirmasi Peminjaman';
$totalUnitDipilih = array_sum(array_map(static function ($item) { return (int)$item['jumlah']; }, $daftarPilihan));
require 'views/layouts/role_header.php';
?>
<div class="app-page-head"><div><h1 class="app-title">Konfirmasi Peminjaman</h1><p class="app-subtitle">Periksa semua barang yang sudah kamu tambahkan, lalu tentukan jumlah unit masing-masing.</p></div><a href="index.php?page=dashboard#katalog" class="btn btn-ghost">Kembali</a></div>
<div class="row justify-content-center"><div class="col-lg-8"><div class="app-card p-0 overflow-hidden"><div class="app-card-header"><h2 class="app-card-title"><i class="bi bi-cart-check me-2"></i>Daftar barang pilihan (<?= count($daftarPilihan); ?> jenis · <?= $totalUnitDipilih; ?> unit)</h2></div><div class="p-4">
<form action="index.php?page=proses-ajukan" method="POST">
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nama alat</th><th>Stok tersedia</th><th>Jumlah pinjam</th></tr></thead><tbody>
<?php foreach ($daftarPilihan as $alat): ?><tr><td class="fw-semibold"><?= htmlspecialchars($alat['nama_alat'], ENT_QUOTES, 'UTF-8'); ?></td><td><?= (int)$alat['stok']; ?> unit</td><td><input class="form-control" type="number" min="1" max="<?= (int)$alat['stok']; ?>" name="alat[<?= (int)$alat['id']; ?>]" value="<?= (int)$alat['jumlah']; ?>" required style="max-width:110px"></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="row g-3 mt-2"><div class="col-md-6"><label class="form-label">Tanggal Pinjam</label><input type="date" name="tanggal_pinjam" class="form-control" value="<?= date('Y-m-d'); ?>" required></div><div class="col-md-6"><label class="form-label">Rencana Tanggal Kembali</label><input type="date" name="tanggal_kembali_seharusnya" class="form-control" min="<?= date('Y-m-d'); ?>" required></div></div>
<div class="alert alert-warning bg-white mt-3"><strong>Aturan denda:</strong> keterlambatan pengembalian dikenakan denda otomatis Rp 20.000 per hari.</div><div class="d-flex gap-2 mt-4"><a href="index.php?page=dashboard#katalog" class="btn btn-ghost flex-fill">Batal</a><button type="submit" class="btn btn-primary flex-fill">Kirim Semua Pengajuan</button></div>
</form></div></div></div></div>
<?php require 'views/layouts/role_footer.php'; ?>

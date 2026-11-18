<?php
$pageTitle = 'Struk Peminjaman';
require 'views/layouts/role_header.php';
$first = $items[0];
$totalJenis = count($items);
$totalUnit = 0;
foreach ($items as $item) $totalUnit += (int) $item['jumlah'];
$statusMap = [
    'pending' => 'MENUNGGU PERSETUJUAN',
    'disetujui' => 'DISETUJUI',
    'menunggu_pengembalian' => 'MENUNGGU PENGEMBALIAN',
    'kembali' => 'SUDAH DIKEMBALIKAN',
    'ditolak' => 'DITOLAK'
];
?>
<style>
.receipt-wrap{max-width:560px;margin:0 auto 28px}.receipt{background:#fff;color:#17212b;border:1px solid #e5e7eb;border-radius:14px;padding:28px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;box-shadow:0 12px 34px rgba(15,23,42,.08)}
.receipt-center{text-align:center}.receipt-brand{font-family:Arial,sans-serif;font-weight:800;font-size:22px;letter-spacing:1px}.receipt-muted{color:#64748b;font-size:12px}.receipt-dash{border:0;border-top:1px dashed #94a3b8;margin:17px 0}.receipt-row{display:flex;justify-content:space-between;gap:12px;font-size:13px;margin:8px 0}.receipt-item{padding:9px 0;border-bottom:1px dashed #e2e8f0}.receipt-item-name{font-size:13px;font-weight:700}.receipt-item-meta{display:flex;justify-content:space-between;font-size:12px;color:#475569;margin-top:5px}.receipt-total{font-weight:800;font-size:14px}.receipt-status{display:inline-block;padding:6px 10px;border:1px solid #cbd5e1;border-radius:4px;font-size:11px;font-weight:800}.receipt-actions{display:flex;gap:10px;justify-content:center;margin-bottom:18px}
@media print{ @page{size:80mm auto;margin:5mm} body{background:#fff!important}.app-sidebar,.app-mobile-top,.app-page-head,.receipt-actions,footer{display:none!important}.app-shell,.app-main,.app-content{display:block!important;margin:0!important;padding:0!important;width:100%!important;max-width:none!important}.receipt-wrap{max-width:none;margin:0}.receipt{border:0;border-radius:0;box-shadow:none;padding:0;font-size:11px}.receipt-brand{font-size:18px}.receipt-row,.receipt-item-name{font-size:11px}.receipt-muted,.receipt-item-meta{font-size:10px}}
</style>
<div class="app-page-head"><div><h1 class="app-title">Struk Peminjaman</h1><p class="app-subtitle">Bukti pengajuan barang dalam satu transaksi.</p></div></div>
<div class="receipt-wrap">
  <div class="receipt-actions"><button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak / Simpan PDF</button><a class="btn btn-ghost" href="index.php?page=dashboard">Kembali</a></div>
  <section class="receipt">
    <div class="receipt-center">
      <div class="receipt-brand">PINJAM ALAT</div>
      <div class="receipt-muted">BUKTI TRANSAKSI PEMINJAMAN</div>
      <div class="receipt-muted">Simpan struk ini sebagai bukti pengajuan</div>
    </div>
    <hr class="receipt-dash">
    <div class="receipt-row"><span>No. Transaksi</span><strong><?= htmlspecialchars($first['kode_transaksi'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="receipt-row"><span>Tanggal transaksi</span><span><?= date('d/m/Y', strtotime($first['tanggal_pinjam'])); ?></span></div>
    <div class="receipt-row"><span>Peminjam</span><strong><?= htmlspecialchars($first['username'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="receipt-row"><span>Tanggal pinjam</span><span><?= date('d/m/Y', strtotime($first['tanggal_pinjam'])); ?></span></div>
    <div class="receipt-row"><span>Rencana kembali</span><span><?= !empty($first['tanggal_kembali_seharusnya']) ? date('d/m/Y', strtotime($first['tanggal_kembali_seharusnya'])) : '-'; ?></span></div>
    <hr class="receipt-dash">
    <div class="receipt-row"><strong>DAFTAR BARANG</strong><strong>JUMLAH</strong></div>
    <?php foreach ($items as $item): ?>
      <div class="receipt-item"><div class="receipt-item-name"><?= htmlspecialchars($item['nama_alat'], ENT_QUOTES, 'UTF-8'); ?></div><div class="receipt-item-meta"><span>Barang dipinjam</span><strong><?= (int)$item['jumlah']; ?> unit</strong></div></div>
    <?php endforeach; ?>
    <hr class="receipt-dash">
    <div class="receipt-row"><span>Total jenis barang</span><strong><?= $totalJenis; ?></strong></div>
    <div class="receipt-row receipt-total"><span>Total seluruh unit</span><span><?= $totalUnit; ?> UNIT</span></div>
    <div class="receipt-row"><span>Status pengajuan</span><span class="receipt-status"><?= htmlspecialchars($statusMap[$first['status_pinjam']] ?? strtoupper($first['status_pinjam']), ENT_QUOTES, 'UTF-8'); ?></span></div>
    <?php
      $sameStatus = true;
      foreach ($items as $item) if ($item['status_pinjam'] !== $first['status_pinjam']) $sameStatus = false;
    ?>
    <?php if (!$sameStatus): ?><p class="receipt-muted">Status tiap barang dapat berbeda. Periksa riwayat peminjaman untuk detail terbaru.</p><?php endif; ?>
    <hr class="receipt-dash">
    <div class="receipt-center"><strong>*** TERIMA KASIH ***</strong><p class="receipt-muted mt-2">Ini adalah struk peminjaman, bukan bukti pembayaran.</p><p class="receipt-muted">Harap tunjukkan nomor transaksi kepada petugas.</p></div>
  </section>
</div>
<?php require 'views/layouts/role_footer.php'; ?>

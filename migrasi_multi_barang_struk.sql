-- Migrasi aman untuk database lama. Tidak menghapus atau mereset data.
DROP PROCEDURE IF EXISTS migrasi_multi_barang_struk;
DELIMITER //
CREATE PROCEDURE migrasi_multi_barang_struk()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='peminjaman' AND COLUMN_NAME='jumlah') THEN
    ALTER TABLE peminjaman ADD COLUMN jumlah INT(11) NOT NULL DEFAULT 1 AFTER alat_id;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='peminjaman' AND COLUMN_NAME='kode_transaksi') THEN
    ALTER TABLE peminjaman ADD COLUMN kode_transaksi VARCHAR(40) NULL AFTER jumlah;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='peminjaman' AND COLUMN_NAME='stok_dikurangi') THEN
    ALTER TABLE peminjaman ADD COLUMN stok_dikurangi TINYINT(1) NOT NULL DEFAULT 1 AFTER kode_transaksi;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='peminjaman' AND INDEX_NAME='idx_peminjaman_kode_transaksi') THEN
    ALTER TABLE peminjaman ADD INDEX idx_peminjaman_kode_transaksi (kode_transaksi);
  END IF;
END//
DELIMITER ;
CALL migrasi_multi_barang_struk();
DROP PROCEDURE migrasi_multi_barang_struk;

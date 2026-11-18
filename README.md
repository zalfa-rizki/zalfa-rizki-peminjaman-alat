# Peminjaman Alat (PHP + MySQL)

Aplikasi berbasis PHP, MySQL/MariaDB, HTML, CSS, dan JavaScript dengan role Admin, Petugas, dan Peminjam. Struktur dan halaman lama tetap dipakai.

## Fitur multi-barang
- Peminjam mengatur jumlah tiap alat, menekan **+ Tambah Barang**, lalu melihat daftar pilihan dan total unit.
- Satu kali pengajuan menyimpan beberapa baris barang dengan satu `kode_transaksi`.
- Validasi stok dilakukan kembali di server dalam transaksi database; stok dikurangi sesuai unit yang diajukan.
- Penolakan mengembalikan stok tiap barang. Pengembalian oleh Petugas mengembalikan stok sesuai jumlah.
- Petugas melihat daftar barang dalam satu transaksi dan persetujuan/penolakan/pengembalian dilakukan untuk satu nomor transaksi.
- Struk digital memuat nomor transaksi, username peminjam, tanggal, daftar barang, jumlah, dan status.
- Tombol cetak membuka dialog cetak Android/browser; pilih **Save as PDF / Simpan sebagai PDF** bila tersedia.

## Database
Jangan impor `database/peminjaman.sql` ke database lama karena file itu adalah dump instalasi baru dan dapat menimpa data bila diimpor sebagai struktur/data.

Untuk database lama:
1. Cadangkan database melalui phpMyAdmin.
2. Pilih database aplikasi yang sudah digunakan.
3. Impor `database/migrasi_multi_barang_struk.sql` satu kali. Migrasi memeriksa kolom dan indeks lebih dahulu, sehingga aman untuk dump yang sudah memiliki `jumlah` dan `kode_transaksi`.
4. File `update_multi_peminjaman.sql` dan `update_struk_transaksi.sql` hanya catatan kompatibilitas; jangan jalankan keduanya terpisah. Gunakan migrasi gabungan di atas.

Untuk instalasi baru, buat database kosong lalu impor `database/peminjaman.sql`.

## Menjalankan di AWebServer Android
1. Ekstrak folder `peminjaman-alat-main` ke folder web root AWebServer (misalnya `htdocs` atau folder root yang dikonfigurasi di aplikasi).
2. Jalankan layanan web server dan MySQL/MariaDB dari AWebServer.
3. Buka phpMyAdmin, buat database bernama `db_peminjaman`.
4. Untuk database baru, impor `database/peminjaman.sql`. Untuk database lama, jangan impor dump; jalankan migrasi gabungan sesuai petunjuk Database.
5. Periksa `config/koneksi.php`: sesuaikan host, username, password, dan nama database dengan konfigurasi MySQL AWebServer.
6. Buka alamat lokal AWebServer dari browser Android, lalu masuk ke `index.php`.
7. Login dengan akun yang memang tersedia di database. Dump contoh menyertakan username `admin`, `petugas`, dan `peminjam`; password mengikuti data yang dibuat pada instalasi awal dan tidak dapat dijamin sama jika database sudah diubah.

## Pengujian
Pemeriksaan sintaks PHP dilakukan pada seluruh file PHP proyek. Uji runtime tetap perlu dilakukan pada perangkat AWebServer karena lingkungan MySQL, versi PHP, konfigurasi folder, dan database pengguna tidak tersedia di lingkungan pemeriksaan ini.

## Catatan keamanan setelah pembaruan
- File koneksi yang digunakan aplikasi adalah `config/koneksi.php`.
- Foto alat dibatasi maksimal 5 MB dan hanya menerima JPG, PNG, atau WEBP yang tervalidasi dari sisi server.
- Penghapusan alat dan pengguna menggunakan POST, prepared statement, dan token CSRF.
- Formulir pengelolaan pengguna dan alat menyertakan token CSRF.

user:
usn:admin pw:12345678
usn:petugas pw:12345678
usn:peminjam pw:12345678

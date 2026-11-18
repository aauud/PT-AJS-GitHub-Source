# Sistem Informasi Program Pelatihan PT AJS

Aplikasi berbasis web untuk mendukung penyampaian
informasi program pelatihan, pendaftaran peserta,
pengelolaan data, transaksi, dan aktivitas pelatihan.

## Teknologi

- PHP 8.2
- PostgreSQL 18
- Bootstrap 5.3.3
- HTML, CSS, JavaScript
- Midtrans Sandbox

## Fitur Utama

### User
- Melihat katalog program pelatihan
- Melihat informasi program
- Registrasi akun
- Mengirimkan pertanyaan

### Member
- Pendaftaran pelatihan
- Pemantauan transaksi dan pembayaran
- Pembelajaran daring asinkron
- Pengiriman bukti kehadiran
- Posttest dan sertifikat

### Admin
- Pengelolaan program dan jadwal
- Pengelolaan materi dan posttest
- Verifikasi kehadiran
- Pemantauan transaksi dan pembayaran
- Pengelolaan pertanyaan masuk

## Persiapan

1. Siapkan PHP dan PostgreSQL.
2. Buat basis data PostgreSQL.
3. Jalankan database/schema.sql.
4. Salin konfigurasi contoh di dalam folder config
   menjadi konfigurasi lokal.
5. Isi informasi koneksi database dan Midtrans Sandbox.
6. Jalankan aplikasi menggunakan PHP Built-in Server.

Lebih mendetail dalam detail_panduan.txt

## Peringatan Proses Pembayaran

Integrasi Midtrans menggunakan Sandbox untuk
keperluan simulasi dan pengujian. Pembayaran
tidak menggunakan dana sebenarnya.

## Catatan

Aplikasi ini dikembangkan sebagai proyek penelitian
untuk PT Arta Jaya Sampurna.

=====================================================================
==           SISTEM INFORMASI PROGRAM PELATIHAN PT AJS             ==
==              PANDUAN PENGGUNAAN DAN PENGATURAN                  ==
=====================================================================

A. DESKRIPSI SISTEM

Aplikasi ini merupakan sistem informasi program
pelatihan berbasis web yang dikembangkan untuk
PT Arta Jaya Sampurna.

Sistem menyediakan fasilitas penyampaian informasi
program pelatihan, pendaftaran peserta, pengelolaan
data pelatihan, transaksi, pembayaran, pembelajaran,
kehadiran, posttest, sertifikat, dan pertanyaan masuk.

B. HAK AKSES

1. User (Guest)
   Pengunjung dapat melihat informasi umum,
   katalog program pelatihan, serta mengirimkan
   pertanyaan melalui halaman Kontak.

2. Member
   Member dapat melakukan pendaftaran pelatihan,
   memantau transaksi dan pembayaran, mengikuti
   aktivitas pembelajaran, mengirimkan bukti
   kehadiran, mengerjakan posttest, serta mengakses
   sertifikat sesuai persyaratan.

3. Admin
   Admin dapat mengelola program pelatihan, jadwal,
   materi, posttest, kehadiran, transaksi, serta
   pertanyaan masuk.

C. LINGKUNGAN PENGEMBANGAN

PHP              : 8.2.12
PostgreSQL       : 18.1
XAMPP Control    : 3.3.0
Bootstrap        : 5.3.3
pgAdmin          : 4
PHP Server       : PHP Built-in Development Server

Browser yang digunakan selama pengujian:
Google Chrome.

D. PERSIAPAN APLIKASI

1. Pastikan PostgreSQL telah dijalankan.
2. Siapkan basis data dengan nama ajs_demo.
3. Pastikan struktur dan data demonstrasi telah
   tersedia pada basis data.
4. Siapkan konfigurasi koneksi basis data dan
   Midtrans Sandbox pada lokasi konfigurasi lokal
   yang tidak berada di dalam direktori publik.
5. Pastikan ekstensi PostgreSQL untuk PHP aktif.

E. MENJALANKAN APLIKASI SECARA LOKAL

Jalankan perintah berikut melalui PowerShell:

& "C:\xampp\php\php.exe" -S 127.0.0.1:8001 -t "C:\xampp\htdocs\ajs_demo" "C:\xampp\htdocs\ajs_demo\router.php"

Aplikasi dapat dibuka melalui:

http://127.0.0.1:8001

Pastikan terminal server tetap berjalan selama
aplikasi digunakan.

F. AKUN DEMONSTRASI

Admin:
Username: demo_admin

Member:
Username: demo_member

Member (transaksi menunggu pembayaran):
Username: demo_pending

Informasi kata sandi diberikan secara terpisah
melalui media komunikasi yang aman.

Pengunjung dapat mengakses halaman publik tanpa
melakukan login.

G. PEMBAYARAN

Integrasi pembayaran menggunakan Midtrans Sandbox
untuk keperluan simulasi dan pengujian.

Pembayaran yang dilakukan pada lingkungan ini
bukan merupakan transaksi keuangan sebenarnya.

Untuk pengujian QRIS, gunakan QRIS Simulator
Midtrans Sandbox. Jangan menggunakan aplikasi
perbankan atau dompet digital untuk membayar
referensi transaksi Sandbox.

PANDUAN SIMULASI QRIS

1. Login menggunakan akun Member.
2. Daftar pada jadwal pelatihan yang tersedia.
3. Pilih metode pembayaran QRIS.
4. Buka halaman detail transaksi dan tampilkan QRIS.
5. Salin alamat gambar QRIS melalui fitur
   "Copy image address" pada browser.
6. Buka Midtrans Sandbox QRIS Simulator:
   https://simulator.sandbox.midtrans.com/v2/qris/index
7. Masukkan alamat gambar QRIS pada kolom
   QR Code Image URL.
8. Pilih Scan QR dan ikuti instruksi simulator.
9. Kembali ke aplikasi dan periksa status
   pembayaran pada detail transaksi.

Catatan:
Pengujian memerlukan server aplikasi dan
endpoint notifikasi yang tetap aktif.
Gunakan simulator Sandbox, bukan aplikasi
pembayaran atau rekening sebenarnya.

H. CATATAN PENGGUNAAN

1. Aplikasi demonstrasi menggunakan data contoh.
2. Status transaksi dan pembayaran merupakan
   data demonstrasi.
3. Beberapa fitur hanya tersedia setelah Member
   memenuhi persyaratan pendaftaran, pembayaran,
   kehadiran, atau penyelesaian materi.
4. Akses aplikasi melalui tautan demonstrasi
   hanya tersedia ketika server lokal dan layanan
   penghubung eksternal sedang berjalan.
5. Aplikasi ini merupakan lingkungan demonstrasi
   dan belum dikonfigurasi sebagai layanan produksi.

============================================================
==                 PT ARTA JAYA SAMPURNA                  ==
============================================================
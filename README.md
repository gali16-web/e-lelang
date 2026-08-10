# E-Lelang SMAN 12 Medan

Aplikasi e-lelang berbasis PHP 8 dan MySQL sebagai media edukasi kewirausahaan digital bagi siswa SMAN 12 Medan. Pemenang lelang ditentukan melalui implementasi Selection Sort secara descending.

## Persyaratan

- XAMPP 8.1 atau lebih baru (PHP 8.1+, Apache, MySQL/MariaDB)
- Ekstensi PHP: PDO MySQL, Fileinfo, dan Mbstring
- Browser modern

## Instalasi di XAMPP

1. Salin folder `e-lelang-sman12-modern` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka phpMyAdmin melalui `http://localhost/phpmyadmin`.
4. Pilih menu **Import**, lalu impor `database/elelang_sman12.sql`.
5. Jika username atau password MySQL berbeda, sesuaikan `config.php`.
6. Buka `http://localhost/e-lelang-sman12-modern/`.

## Akun administrator awal

- Username: `admin`
- Kata sandi: `Admin123!`

Segera ubah kata sandi melalui menu Profil setelah berhasil masuk.

## Alur penggunaan

1. Siswa, guru, atau staf melakukan registrasi.
2. Administrator menyetujui akun.
3. Pengguna mengajukan barang beserta foto dan harga awal.
4. Administrator memverifikasi barang dan membuat jadwal lelang.
5. Pengguna selain pemilik barang memberikan penawaran selama periode berlangsung.
6. Setiap daftar penawaran diurutkan secara descending menggunakan Selection Sort.
7. Administrator menutup lelang; sistem menetapkan penawar tertinggi sebagai pemenang.
8. Pemenang mengunggah bukti pembayaran ke rekening penjual.
9. Administrator memverifikasi pembayaran.
10. Penjual dan administrator mengatur distribusi barang sampai selesai.

## Implementasi Selection Sort

Algoritma terdapat di `app/SelectionSorter.php`. Fungsi `descending()` mengurutkan nominal dari terbesar ke terkecil. Fungsi `withTrace()` juga menghasilkan jejak setiap iterasi untuk ditampilkan pada detail lelang dan digunakan dalam pengujian skripsi.

Aturan nilai sama: penawaran yang masuk lebih dahulu didahulukan. Pada alur normal, sistem mewajibkan penawaran baru lebih tinggi sekurang-kurangnya sebesar kenaikan minimal sehingga nominal sama akan ditolak.

## Keamanan yang diterapkan

- PDO prepared statements
- `password_hash()` dan `password_verify()`
- perlindungan CSRF
- validasi peran dan hak akses
- escaping keluaran HTML
- validasi MIME dan ukuran gambar
- session cookie HttpOnly dan SameSite
- audit log tindakan penting

## Catatan

Folder `uploads/items` dan `uploads/payments` harus dapat ditulis oleh web server. Pada XAMPP Windows, pengaturan bawaan biasanya sudah memadai.

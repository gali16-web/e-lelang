# Panduan Pengujian Sistem

## Pengujian algoritma

Jalankan dari Command Prompt XAMPP:

```text
C:\xampp\php\php.exe tests\SelectionSorterTest.php
```

Hasil yang diharapkan:

```text
BERHASIL: Selection Sort menghasilkan urutan descending yang benar.
Hasil: 1750000, 1500000, 1250000, 1100000
Jumlah iterasi: 3
```

## Skenario Black Box utama

| No. | Fitur | Skenario | Hasil yang diharapkan |
|---|---|---|---|
| 1 | Registrasi | Mengisi data valid | Akun tersimpan dengan status menunggu |
| 2 | Persetujuan akun | Admin mengaktifkan akun | Pengguna dapat login |
| 3 | Login | Memasukkan kata sandi salah | Sistem menolak login |
| 4 | Barang | Pengguna mengajukan barang valid | Barang menunggu verifikasi |
| 5 | Verifikasi barang | Admin menyetujui barang | Barang dapat dijadwalkan |
| 6 | Jadwal lelang | Admin membuat periode valid | Lelang tampil sesuai waktu |
| 7 | Penawaran | Nominal memenuhi batas minimal | Penawaran tersimpan |
| 8 | Validasi penawaran | Nominal lebih rendah dari batas | Penawaran ditolak |
| 9 | Kepemilikan | Pemilik menawar barang sendiri | Penawaran ditolak |
| 10 | Selection Sort | Mengurutkan beberapa nominal | Data terurut descending |
| 11 | Penutupan lelang | Admin menutup lelang | Peringkat pertama menjadi pemenang |
| 12 | Pembayaran | Pemenang mengunggah gambar valid | Bukti menunggu verifikasi |
| 13 | Distribusi | Penjual menyelesaikan penyerahan | Status menjadi selesai |
| 14 | Hak akses | Pengguna membuka halaman admin | Sistem menolak akses |
| 15 | Laporan | Admin memilih jenis laporan | Data tampil dan dapat diekspor CSV |

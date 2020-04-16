# Plugin Mailketing untuk Mautic

[![license](https://img.shields.io/circleci/project/github/KonstantinCodes/mautic-recaptcha.svg)](https://circleci.com/gh/KonstantinCodes/mautic-recaptcha/tree/master) [![license](https://img.shields.io/packagist/v/koco/mautic-recaptcha-bundle.svg)](https://packagist.org/packages/koco/mautic-recaptcha-bundle)
[![Packagist](https://img.shields.io/packagist/l/koco/mautic-recaptcha-bundle.svg)](LICENSE) [![mautic](https://img.shields.io/badge/mautic-%3E%3D%202.15.2-blue.svg)](https://www.mautic.org/mixin/recaptcha/)

Untuk Mautic 2.15 Keatas

Licensed under GNU General Public License v3.0.


Silahkan Cek Video Tutorial disini >> https://youtu.be/bSJqLHboviM yang di buat oleh https://mauticloud.email

## Installation Step #1
1. Masuk Putty / ssh console dan menuju directori mautic anda
2. Execute `composer require pekanbarugamer/mauticmailketingbundle`
3. Apabila sukses / terjadi error karena memory tidak cukup, tidak masalah, bisa lanjut ke step selanjutnya

## Installation Step #2
1. Masuk ke folder plugins `cd plugins`
2. download file plugins mailketing `wget https://app.mailketing.co.id/MauticMailketingBundle.zip`
3. Unzip file tersebut `unzip MauticMailketingBundle.zip`
4. Bisa hapus file zip `rm MauticMailketingBundle.zip`
5. Kembali ke Directory utama mautic `cd ..`
4. Clear cache mautic dengan command `php app/console cache:clear`

## Installation Step #3
1. Buka Menu Plugins pada Web Anda
2. Kemudian Klik "Install/Upgrade Plugins"
3. Logo Mailketing akan muncul sebagai plugins yang menandakan instalasi sudah berhasil

## Configuration
1. Silahkan menuju ke halaman Configuration pada Mautic Anda
2. Menuju ke Email Setting Section
3. Anda akan melihat "Mailketing - API" sebagai opsi pengiriman
4. Silahkan Masukkan token dari mailketing, apply, lalu test connection dan send test email.

Dan anda sudah berhasil menggunakan mailketing api dan setiap pengiriman dari mautic anda akan melalui smtp mailketing

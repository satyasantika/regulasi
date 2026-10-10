// Tangkapan layar panduan per peran (F12.6).
// Jalankan: composer panduan  (atau docker compose -f docker-compose.panduan.yml --profile panduan run --rm regulasi-panduan)
// Prasyarat lokal: php artisan migrate:fresh --seeder=PanduanSeeder ; php artisan regulasi:reindeks ;
//                  PANDUAN_PASSWORD sama dengan .env.
// Keluaran: public/panduan/img/<peran>/NN-<langkah>.png (viewport 1366×768; alur ponsel 390×844), dikompres
//           dengan palet terbatas (PNG 8-bit) oleh skrip ini bila modul "sharp" tersedia, selain itu PNG apa adanya.
// Argumen opsional: daftar peran (umum, pengguna, kontributor, admin-regulasi, super-admin).
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ?? 'playwright');
const sharp = await import(process.env.SHARP_MODULE ?? 'sharp').then((m) => m.default).catch(() => null);

const BASE = (process.env.PANDUAN_BASE_URL ?? 'http://localhost:8034/regulasi').replace(/\/$/, '');
const SANDI = process.env.PANDUAN_PASSWORD;
const KELUARAN = new URL('../../public/panduan/img/', import.meta.url).pathname;

if (!SANDI) {
    console.error('PANDUAN_PASSWORD wajib diisi.');
    process.exit(1);
}

const LAYAR = { width: 1366, height: 768 };
const PONSEL = { width: 390, height: 844 };
const BATAS_TINGGI = 2600; // tangkapan halaman penuh dipotong agar berkas tetap kecil

async function tunggu(page) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
}

async function buka(page, path) {
    await page.goto(BASE + path);
    await tunggu(page);
}

/** Buka tautan pertama yang href-nya memuat `pola` dan berada di elemen yang memuat `teks`. */
async function bukaTautan(page, path, pola, teks, akhiran = '') {
    await buka(page, path);
    const wadah = teks ? page.locator('tr, li, article, div.rounded-xl, a', { hasText: teks }) : page.locator('body');
    const href = await wadah
        .locator(`a[href*="${pola}"]`)
        .evaluateAll((el) => el.map((a) => a.getAttribute('href')).filter((h) => /[0-9a-f]{8}-[0-9a-f-]{27}/.test(h)))
        .then((h) => h[0])
        .catch(() => null);
    const sendiri = teks ? await page.locator(`a[href*="${pola}"]`, { hasText: teks }).first().getAttribute('href').catch(() => null) : null;
    const tujuan = href ?? sendiri;
    if (!tujuan) throw new Error(`tautan ${pola} "${teks}" tidak ditemukan di ${path}`);
    const url = tujuan.replace(/\/(edit)?$/, '') + akhiran;
    await page.goto(url.startsWith('http') ? url : BASE + url);
    await tunggu(page);
    await muatBawah(page);
}

/** Relation manager Filament dimuat malas: gulir ke bawah dan tunggu tabelnya tampil. */
async function muatBawah(page) {
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(1500);
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(300);
}

const ALUR = {
    umum: [
        ['beranda', (p) => buka(p, '/'), { penuh: true }],
        ['daftar-dokumen', (p) => buka(p, '/dokumen?kategori[0]=akreditasi&status[0]=berlaku')],
        ['detail-dokumen', (p) => bukaTautan(p, '/dokumen?q=BAN-PT%2013%202025', '/dokumen/', '13/2025'), { penuh: true }],
        ['detail-dicabut', (p) => bukaTautan(p, '/dokumen?q=Permendikbud%203%202021', '/dokumen/', '3/2021')],
        ['masuk', (p) => buka(p, '/akun/login')],
        ['ponsel-beranda', (p) => buka(p, '/'), { ponsel: true }],
        ['ponsel-daftar', (p) => buka(p, '/dokumen?kategori[0]=aset'), { ponsel: true }],
    ],
    pengguna: [
        ['dasbor-akun', (p) => buka(p, '/akun')],
        ['pencarian-internal', (p) => buka(p, '/dokumen?q=ruang%20rapat')],
        ['dokumen-internal', (p) => bukaTautan(p, '/dokumen?q=ruang%20rapat', '/dokumen/', 'Ruang Rapat'), { penuh: true }],
        ['profil', (p) => buka(p, '/akun/profile')],
        ['ponsel-dokumen-internal', (p) => bukaTautan(p, '/dokumen?q=ruang%20rapat', '/dokumen/', 'Ruang Rapat'), { ponsel: true }],
    ],
    kontributor: [
        ['dasbor', (p) => buka(p, '/admin')],
        ['daftar-dokumen', (p) => buka(p, '/admin/dokumen')],
        ['buat-dokumen', (p) => buka(p, '/admin/dokumen/create'), { penuh: true }],
        ['ubah-draf', (p) => bukaTautan(p, '/admin/dokumen?search=Peminjaman', '/admin/dokumen/', 'Peminjaman', '/edit'), { penuh: true }],
        [
            'tambah-tautan',
            async (p) => {
                await bukaTautan(p, '/admin/dokumen?search=Peminjaman', '/admin/dokumen/', 'Peminjaman', '/edit');
                await p.getByRole('button', { name: 'Tambah tautan' }).first().click({ timeout: 15000 });
                await p.waitForTimeout(900);
            },
        ],
        ['dokumen-ditolak', (p) => bukaTautan(p, '/admin/dokumen?search=Penghapusan', '/admin/dokumen/', 'Penghapusan', '/edit')],
        ['laporan-kelengkapan', (p) => buka(p, '/admin/laporan/kelengkapan')],
    ],
    'admin-regulasi': [
        ['dasbor', (p) => buka(p, '/admin'), { penuh: true }],
        ['antrean-verifikasi', (p) => buka(p, '/admin/antrean-verifikasi')],
        ['verifikasi-dokumen', (p) => bukaTautan(p, '/admin/dokumen?search=Inventarisasi', '/admin/dokumen/', 'Inventarisasi'), { penuh: true }],
        [
            'relasi-dokumen',
            async (p) => {
                await bukaTautan(p, '/admin/dokumen?search=Instrumen%20Akreditasi%20Program%20Studi%20(IAPS)%205.0', '/admin/dokumen/', 'BAN-PT 13/2025');
                const tab = p.getByRole('tab', { name: 'Relasi' }).first();
                await tab.click();
                await p.waitForTimeout(1500);
                await tab.evaluate((el) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 80));
                await p.waitForTimeout(400);
            },
        ],
        ['tautan-bermasalah', (p) => buka(p, '/admin/tautan-bermasalah')],
        ['laporan-statistik', (p) => buka(p, '/admin/laporan/statistik')],
        ['laporan-evaluasi', (p) => bukaTautan(p, '/admin/laporan-evaluasi', '/admin/laporan-evaluasi/', 'Semester Genap'), { penuh: true }],
        ['master-jenis-dokumen', (p) => buka(p, '/admin/jenis-dokumen')],
    ],
    'super-admin': [
        ['pengguna', (p) => buka(p, '/admin/pengguna')],
        ['buat-pengguna', (p) => buka(p, '/admin/pengguna/create'), { penuh: true }],
        ['klien-api', (p) => bukaTautan(p, '/admin/klien-api', '/admin/klien-api/', 'Sistem Aset'), { penuh: true }],
        ['log-aktivitas', (p) => buka(p, '/admin/log-aktivitas')],
        ['horizon', (p) => buka(p, '/horizon')],
    ],
};

async function masuk(page, peran) {
    const panel = peran === 'pengguna' ? 'akun' : 'admin';
    await page.goto(`${BASE}/${panel}/login`);
    await page.fill('input[type=email]', `panduan.${peran}@contoh.test`);
    await page.fill('input[type=password]', SANDI);
    await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 30000 }), page.click('button[type=submit]')]);
    await tunggu(page);
}

/** Kompres PNG (palet 8-bit) dan batasi lebar 1366 px. */
async function kompres(berkas) {
    if (!sharp) return;
    const masukan = await readFile(berkas);
    const keluaran = await sharp(masukan).resize({ width: 1366, withoutEnlargement: true }).png({ palette: true, quality: 85, compressionLevel: 9, effort: 8 }).toBuffer();
    if (keluaran.length < masukan.length) await writeFile(berkas, keluaran);
}

const peranDipilih = process.argv.slice(2);
const browser = await chromium.launch();
let gagal = 0;

for (const [peran, langkah] of Object.entries(ALUR)) {
    if (peranDipilih.length && !peranDipilih.includes(peran)) continue;
    await mkdir(KELUARAN + peran, { recursive: true });

    for (const ponsel of [false, true]) {
        const daftar = langkah.map((l, i) => [i, ...l]).filter(([, , , o]) => Boolean(o?.ponsel) === ponsel);
        if (daftar.length === 0) continue;

        const konteks = await browser.newContext({ viewport: ponsel ? PONSEL : LAYAR, locale: 'id-ID', timezoneId: 'Asia/Jakarta', deviceScaleFactor: 1 });
        const page = await konteks.newPage();
        if (peran !== 'umum') await masuk(page, peran);

        for (const [i, nama, siapkan, opsi] of daftar) {
            const berkas = `${KELUARAN}${peran}/${String(i + 1).padStart(2, '0')}-${nama}.png`;
            try {
                await siapkan(page);
                const tinggi = await page.evaluate(() => document.documentElement.scrollHeight);
                const penuh = Boolean(opsi?.penuh);
                const klip = penuh && tinggi > BATAS_TINGGI ? { x: 0, y: 0, width: page.viewportSize().width, height: BATAS_TINGGI } : undefined;
                // Samarkan isian kata sandi, kode MFA, dan token bila tampil.
                await page.screenshot({
                    path: berkas,
                    fullPage: penuh,
                    clip: klip,
                    mask: [page.locator('input[type=password]'), page.locator('input[autocomplete="one-time-code"]'), page.locator('code:has-text("|")')],
                });
                await kompres(berkas);
                console.log('✓', berkas.replace(KELUARAN, 'img/'));
            } catch (e) {
                gagal++;
                console.error('✗', peran, nama, e.message.split('\n')[0]);
            }
        }

        await konteks.close();
    }
}

await browser.close();
process.exit(gagal ? 1 : 0);

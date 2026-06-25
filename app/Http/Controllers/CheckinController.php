<?php

namespace App\Http\Controllers;

use App\Models\Pendaftaran;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    /**
     * Tampilkan form login untuk halaman scanner
     */
    public function showLogin()
    {
        if (session('scanner_logged_in')) {
            return redirect()->route('scanner.index');
        }
        return view('scanner.login');
    }

    /**
     * Proses login scanner
     */
    public function processLogin(Request $request)
    {
        $request->validate(['password' => 'required']);

        if ($request->password === '111213') {
            session(['scanner_logged_in' => true]);
            return redirect()->route('scanner.index');
        }

        return back()->withErrors(['password' => 'Password salah.']);
    }

    /**
     * Tampilkan antarmuka scanner
     */
    public function showScanner()
    {
        if (!session('scanner_logged_in')) {
            return redirect()->route('scanner.login');
        }
        
        // Count today's check-ins
        $totalHadir = Pendaftaran::where('status_kehadiran', 'hadir')->count();
        
        return view('scanner.index', compact('totalHadir'));
    }

    /**
     * Proses scan via API (AJAX)
     */
    public function processScan(Request $request)
    {
        if (!session('scanner_logged_in')) {
            return response()->json(['success' => false, 'message' => 'Sesi habis, silakan refresh halaman dan login kembali.'], 401);
        }

        $kode = trim($request->input('kode_registrasi'));
        
        if (empty($kode)) {
            return response()->json(['success' => false, 'message' => 'Kode registrasi kosong.']);
        }

        $pendaftaran = Pendaftaran::where('kode_registrasi', $kode)->first();

        // Jika tidak ditemukan dengan kode_registrasi, coba cari dengan siswa_id
        if (!$pendaftaran) {
            $pendaftaran = Pendaftaran::where('siswa_id', $kode)->first();
        }

        // Jika masih tidak ditemukan, coba cari dengan nama (LIKE)
        if (!$pendaftaran) {
            $hasilNama = Pendaftaran::where('nama_lengkap', 'LIKE', '%' . $kode . '%')->get();
            
            if ($hasilNama->count() === 1) {
                $pendaftaran = $hasilNama->first();
            } elseif ($hasilNama->count() > 1) {
                // Kembalikan daftar pilihan supaya admin bisa memilih
                $pilihan = $hasilNama->map(function($p) {
                    return [
                        'id' => $p->id,
                        'kode_registrasi' => $p->kode_registrasi,
                        'nama' => $p->nama_lengkap,
                        'jadwal' => ($p->jadwal_hari ?: '-') . ' - ' . ($p->jadwal_jam ?: '-'),
                        'status' => $p->status_kehadiran,
                    ];
                });
                return response()->json([
                    'success' => false,
                    'multiple_results' => true,
                    'message' => 'Ditemukan ' . $hasilNama->count() . ' peserta dengan nama tersebut. Silakan pilih:',
                    'data' => $pilihan
                ]);
            }
        }

        if (!$pendaftaran) {
            return response()->json([
                'success' => false, 
                'message' => 'Data tidak ditemukan. Coba dengan kode registrasi, ID siswa, atau nama lengkap.'
            ]);
        }

        if ($pendaftaran->status_kehadiran === 'hadir') {
            return response()->json([
                'success' => false,
                'message' => 'sudah scand barcode kehadiran',
                'data' => [
                    'nama' => $pendaftaran->nama_lengkap,
                    'waktu_checkin' => $pendaftaran->waktu_checkin ? $pendaftaran->waktu_checkin->format('H:i:s') : null
                ]
            ]);
        }

        // Pengecekan kesesuaian jadwal
        if (!$request->input('confirm_override')) {
            $isWrongSchedule = false;
            $warningMessage = '';

            if ($pendaftaran->jadwal_hari) {
                // Cek tanggal jika format mengandung DD/MM/YYYY
                preg_match('/(\d{2}\/\d{2}\/\d{4})/', $pendaftaran->jadwal_hari, $matches);
                if (!empty($matches)) {
                    $scheduledDate = $matches[1];
                    $todayDate = now()->format('d/m/Y');
                    
                    if ($scheduledDate !== $todayDate) {
                        $isWrongSchedule = true;
                        $warningMessage = 'HARI TIDAK SESUAI! Jadwal peserta ini adalah ' . $pendaftaran->jadwal_hari . ' jam ' . ($pendaftaran->jadwal_jam ?: '-') . '.';
                    } else if ($pendaftaran->jadwal_jam) {
                        preg_match('/(\d{2})[.:]/', $pendaftaran->jadwal_jam, $jamMatches);
                        if (!empty($jamMatches)) {
                            $scheduledHour = (int) $jamMatches[1];
                            $currentHour = (int) now()->format('H');
                            
                            // Toleransi kedatangan: boleh beda 1 jam (misal jadwal 09.00, datang jam 08.xx - 10.xx tidak masalah)
                            if (abs($currentHour - $scheduledHour) > 1) {
                                $isWrongSchedule = true;
                                $warningMessage = 'JAM TIDAK SESUAI! Jadwal peserta ini adalah jam ' . $pendaftaran->jadwal_jam . '. Sekarang jam ' . now()->format('H:i') . '.';
                            }
                        }
                    }
                }
            }

            if ($isWrongSchedule) {
                return response()->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'warning_message' => $warningMessage,
                    'data' => [
                        'nama' => $pendaftaran->nama_lengkap,
                        'jadwal_hari' => $pendaftaran->jadwal_hari,
                        'jadwal_jam' => $pendaftaran->jadwal_jam
                    ]
                ]);
            }
        }

        // Tandai hadir
        $pendaftaran->update([
            'status_kehadiran' => 'hadir',
            'waktu_checkin' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kehadiran berhasil dicatat!',
            'data' => [
                'nama' => $pendaftaran->nama_lengkap,
                'jalur' => $pendaftaran->is_umum ? 'UMUM' : 'DARELIMAN',
                'kode' => $pendaftaran->kode_registrasi,
                'jadwal_hari' => $pendaftaran->jadwal_hari,
                'jadwal_jam' => $pendaftaran->jadwal_jam
            ]
        ]);
    }

    public function showMerchandiseScanner()
    {
        if (!session('scanner_logged_in')) {
            return redirect()->route('scanner.login');
        }
        
        $totalHadiah = Pendaftaran::where('status_hadiah', 'sudah')->count();
        
        return view('scanner.merchandise', compact('totalHadiah'));
    }

    public function processMerchandise(Request $request)
    {
        if (!session('scanner_logged_in')) {
            return response()->json(['success' => false, 'message' => 'Sesi habis, silakan refresh halaman dan login kembali.'], 401);
        }

        $kode = trim($request->input('kode_registrasi'));
        
        if (empty($kode)) {
            return response()->json(['success' => false, 'message' => 'Kode registrasi kosong.']);
        }

        $pendaftaran = Pendaftaran::where('kode_registrasi', $kode)->first();

        // Jika tidak ditemukan dengan kode_registrasi, coba cari dengan siswa_id
        if (!$pendaftaran) {
            $pendaftaran = Pendaftaran::where('siswa_id', $kode)->first();
        }

        // Jika masih tidak ditemukan, coba cari dengan nama (LIKE)
        if (!$pendaftaran) {
            $hasilNama = Pendaftaran::where('nama_lengkap', 'LIKE', '%' . $kode . '%')->get();
            
            if ($hasilNama->count() === 1) {
                $pendaftaran = $hasilNama->first();
            } elseif ($hasilNama->count() > 1) {
                $pilihan = $hasilNama->map(function($p) {
                    return [
                        'id' => $p->id,
                        'kode_registrasi' => $p->kode_registrasi,
                        'nama' => $p->nama_lengkap,
                        'jadwal' => ($p->jadwal_hari ?: '-') . ' - ' . ($p->jadwal_jam ?: '-'),
                        'status' => $p->status_kehadiran,
                        'status_hadiah' => $p->status_hadiah,
                    ];
                });
                return response()->json([
                    'success' => false,
                    'multiple_results' => true,
                    'message' => 'Ditemukan ' . $hasilNama->count() . ' peserta. Silakan pilih:',
                    'data' => $pilihan
                ]);
            }
        }

        if (!$pendaftaran) {
            return response()->json([
                'success' => false, 
                'message' => 'Data tidak ditemukan. Coba dengan kode registrasi, ID siswa, atau nama lengkap.'
            ]);
        }

        // Syarat 1: Harus sudah check-in (hadir)
        if ($pendaftaran->status_kehadiran !== 'hadir') {
            return response()->json([
                'success' => false,
                'message' => 'belum checkin',
                'submessage' => 'Peserta ini belum mendaftar kehadiran di meja depan!'
            ]);
        }

        // Syarat 2: Belum ambil hadiah
        if ($pendaftaran->status_hadiah === 'sudah') {
            return response()->json([
                'success' => false,
                'message' => 'sudah ambil hadiah',
                'data' => [
                    'nama' => $pendaftaran->nama_lengkap,
                    'waktu_ambil' => $pendaftaran->waktu_ambil_hadiah ? $pendaftaran->waktu_ambil_hadiah->format('H:i:s') : null
                ]
            ]);
        }

        // Tandai sudah ambil
        $pendaftaran->update([
            'status_hadiah' => 'sudah',
            'waktu_ambil_hadiah' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'HADIAH BISA DIBERIKAN!',
            'data' => [
                'nama' => $pendaftaran->nama_lengkap,
                'jalur' => $pendaftaran->is_umum ? 'UMUM' : 'DARELIMAN',
                'kode' => $pendaftaran->kode_registrasi
            ]
        ]);
    }
}

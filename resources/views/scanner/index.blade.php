@extends('layouts.app')
@section('title', 'Scanner Kehadiran')

@section('content')
<div class="card" style="max-width: 800px; margin: 0 auto; width: 100%;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <h2 class="section-title" style="margin: 0; color: var(--primary);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
            Scanner Kehadiran
        </h2>
        <div style="font-weight: bold; color: var(--success); background: var(--success-light); padding: 0.5rem 1rem; border-radius: 20px;">
            Hadir: <span id="counterHadir">{{ $totalHadir }}</span> Anak
        </div>
    </div>

    <!-- Alert Area (Dynamically Populated) -->
    <div id="alertArea" style="display: none; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center;">
        <h3 id="alertTitle" style="margin-top: 0; margin-bottom: 0.5rem;"></h3>
        <p id="alertMessage" style="margin: 0; font-size: 1.1rem;"></p>
        <div id="alertSubMessage" style="margin-top: 0.5rem; font-weight: bold; font-size: 1.25rem;"></div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 2rem;">
        
        <!-- Input Manual (Untuk Barcode Gun / Scanner Fisik) -->
        <div style="background: var(--bg-page); padding: 1.5rem; border-radius: var(--radius-sm); border: 1px dashed var(--border);">
            <h3 style="margin-top: 0; font-size: 1rem;">Gunakan Barcode Scanner Laser:</h3>
            <form id="scanForm" onsubmit="handleManualScan(event)">
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="manualInput" class="form-input" placeholder="Scan barcode / ketik ID / ketik nama..." autofocus autocomplete="off" style="font-family: monospace; font-size: 1.25rem; font-weight: bold;">
                    <button type="submit" class="btn-primary" style="white-space: nowrap;">Proses</button>
                </div>
            </form>
            <p class="text-muted small" style="margin-bottom: 0; margin-top: 0.5rem;">Pastikan kursor berkedip di dalam kotak di atas sebelum menembak barcode.</p>
        </div>

        <!-- Kamera HP (Html5Qrcode) -->
        <div style="background: #000; border-radius: var(--radius-sm); overflow: hidden; position: relative; min-height: 300px;">
            <!-- Tombol Switch Kamera (Posisi Kanan Atas) -->
            <button type="button" onclick="switchCamera()" style="position: absolute; top: 10px; right: 10px; z-index: 5; background: rgba(0,0,0,0.5); color: white; border: 1px solid white; padding: 0.5rem 0.75rem; border-radius: 20px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                Ganti Kamera
            </button>
            <div id="reader" style="width: 100%; border: none; min-height: 300px;"></div>
            <div id="cameraOverlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 10;">
                <button onclick="startCamera()" class="btn-primary" style="background: var(--success); cursor: pointer; padding: 1rem 2rem; font-size: 1.1rem;">

                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Buka Kamera HP
                </button>
            </div>
        </div>

    </div>
</div>
@endsection

@stack('styles')
<style>
    @media (min-width: 1024px) {
        main.container { max-width: 900px !important; }
    }
</style>

@stack('scripts')
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Pustaka Html5Qrcode dari CDN -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
    let isProcessing = false;
    let html5QrcodeInstance = null;
    let currentCameraIndex = 0;
    let availableCameras = [];

    // Switch camera
    async function switchCamera() {
        if (availableCameras.length <= 1) {
            Swal.fire({
                icon: 'info',
                title: 'Info',
                text: 'Hanya ada 1 kamera yang terdeteksi di perangkat ini.'
            });
            return;
        }

        if (html5QrcodeInstance && html5QrcodeInstance.isScanning) {
            await html5QrcodeInstance.stop();
        }

        currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
        startWithCameraId(availableCameras[currentCameraIndex].id);
    }

    function startWithCameraId(cameraId) {
        html5QrcodeInstance.start(
            cameraId,
            {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            },
            onScanSuccess,
            onScanFailure
        ).catch(err => {
            console.error('Gagal memulai kamera:', err);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Membuka Kamera',
                text: 'Pastikan Anda mengizinkan akses kamera pada browser.'
            });
        });
    }

    async function startCamera() {
        document.getElementById('cameraOverlay').style.display = 'none';

        html5QrcodeInstance = new Html5Qrcode("reader");

        try {
            availableCameras = await Html5Qrcode.getCameras();

            if (!availableCameras || availableCameras.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kamera Tidak Ditemukan',
                    text: 'Tidak ada kamera yang terdeteksi di perangkat ini.'
                });
                return;
            }

            // Cari kamera belakang (back/environment)
            let backCameraIndex = availableCameras.findIndex(camera =>
                camera.label.toLowerCase().includes('back') ||
                camera.label.toLowerCase().includes('belakang') ||
                camera.label.toLowerCase().includes('rear') ||
                camera.label.toLowerCase().includes('environment')
            );

            // Jika tidak ada label yang cocok dan ada lebih dari 1 kamera, pilih kamera terakhir (biasanya belakang)
            if (backCameraIndex === -1 && availableCameras.length > 1) {
                backCameraIndex = availableCameras.length - 1;
            } else if (backCameraIndex === -1) {
                backCameraIndex = 0;
            }

            currentCameraIndex = backCameraIndex;
            startWithCameraId(availableCameras[currentCameraIndex].id);

        } catch (err) {
            console.error('Error getting cameras:', err);
            // Fallback: langsung pakai facingMode environment
            html5QrcodeInstance.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                onScanSuccess,
                onScanFailure
            ).catch(e => {
                console.error('Fallback camera juga gagal:', e);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Membuka Kamera',
                    text: 'Pastikan Anda mengizinkan akses kamera pada browser.'
                });
            });
        }
    }

    async function sendScanData(kode) {
        if (isProcessing) return;
        isProcessing = true;
        await submitKode(kode);
    }

    async function submitKode(kode) {
        if (!kode) return;

        // Pause camera scanner if running
        if (html5QrcodeInstance && html5QrcodeInstance.isScanning) {
            try { await html5QrcodeInstance.pause(true); } catch(e) {}
        }

        document.getElementById('manualInput').disabled = true;

        await performCheckin(kode);
    }

    async function performCheckin(kode, confirmOverride = false) {
        try {
            const response = await fetch('{{ route("scanner.process") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ kode_registrasi: kode, confirm_override: confirmOverride })
            });

            const data = await response.json();

            if (data.requires_confirmation) {
                const confirmResult = await Swal.fire({
                    title: '⚠️ PERINGATAN JADWAL',
                    html: `<b>Nama:</b> ${data.data.nama}<br><br><span style="color: var(--danger); font-size: 1.1rem; font-weight: bold;">${data.warning_message}</span><br><br>Apakah Anda ingin tetap menyimpan absensi kehadiran ini?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Lanjutkan Check-In',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: 'var(--success)',
                    cancelButtonColor: 'var(--danger)',
                    reverseButtons: true
                });

                if (confirmResult.isConfirmed) {
                    await performCheckin(kode, true);
                    return;
                } else {
                    resumeScanner();
                    return;
                }
            }

            // Handle multiple results (pencarian nama)
            if (data.multiple_results) {
                let listHtml = '<div style="text-align:left; max-height:300px; overflow-y:auto;">';
                data.data.forEach((p, i) => {
                    const statusBadge = p.status === 'hadir' 
                        ? '<span style="color:#059669;font-weight:bold;">✅ Sudah Hadir</span>' 
                        : '<span style="color:#dc2626;">Belum Hadir</span>';
                    listHtml += `
                        <label style="display:flex; align-items:flex-start; gap:0.75rem; padding:0.75rem; margin-bottom:0.5rem; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='#f0f7fc'" onmouseout="this.style.background='white'">
                            <input type="radio" name="pilihPeserta" value="${p.kode_registrasi}" style="margin-top:4px;" ${i===0 ? 'checked' : ''}>
                            <div>
                                <div style="font-weight:bold; font-size:1rem;">${p.nama}</div>
                                <div style="font-size:0.85rem; color:#6b7280;">📅 ${p.jadwal} · ${statusBadge}</div>
                            </div>
                        </label>`;
                });
                listHtml += '</div>';

                const pilihResult = await Swal.fire({
                    title: '🔍 Ditemukan ' + data.data.length + ' Peserta',
                    html: listHtml,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Pilih & Check-In',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: 'var(--success)',
                    cancelButtonColor: 'var(--danger)',
                    preConfirm: () => {
                        const selected = document.querySelector('input[name="pilihPeserta"]:checked');
                        if (!selected) {
                            Swal.showValidationMessage('Pilih salah satu peserta');
                            return false;
                        }
                        return selected.value;
                    }
                });

                if (pilihResult.isConfirmed && pilihResult.value) {
                    await performCheckin(pilihResult.value);
                    return;
                } else {
                    resumeScanner();
                    return;
                }
            }

            document.getElementById('manualInput').value = '';

            if (data.success) {
                const counter = document.getElementById('counterHadir');
                counter.innerText = parseInt(counter.innerText) + 1;

                await Swal.fire({
                    title: '✅ BERHASIL CHECK-IN',
                    html: `<b>Nama:</b> ${data.data.nama}<br><b>Jalur:</b> ${data.data.jalur}<br><br><span style="color:var(--primary);"><b>Jadwal:</b> ${data.data.jadwal_hari ? data.data.jadwal_hari : '-'} - ${data.data.jadwal_jam ? data.data.jadwal_jam : '-'}</span>`,
                    icon: 'success',
                    confirmButtonText: 'Lanjut Scan',
                    confirmButtonColor: 'var(--success)'
                });
            } else {
                if (data.message === 'sudah scand barcode kehadiran') {
                    await Swal.fire({
                        title: '❌ SUDAH SCAN',
                        html: `Peserta ini sudah absen kehadiran sebelumnya!<br><br><b>Nama:</b> ${data.data ? data.data.nama : '-'}<br><b>Jam Absen:</b> ${data.data && data.data.waktu_checkin ? data.data.waktu_checkin : '-'}`,
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: 'var(--primary)'
                    });
                } else {
                    await Swal.fire({
                        title: '❌ GAGAL',
                        text: data.message,
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: 'var(--primary)'
                    });
                }
            }

        } catch (error) {
            console.error('Error:', error);
            await Swal.fire({
                title: 'Kesalahan Sistem',
                text: 'Terjadi kesalahan saat menghubungi server.',
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: 'var(--primary)'
            });
        } finally {
            resumeScanner();
        }
    }

    function resumeScanner() {
        const input = document.getElementById('manualInput');
        input.disabled = false;
        input.focus();

        if (html5QrcodeInstance && html5QrcodeInstance.getState() === Html5QrcodeScannerState.PAUSED) {
            try { html5QrcodeInstance.resume(); } catch(e) {}
        }
        isProcessing = false;
    }

    function handleManualScan(e) {
        e.preventDefault();
        const input = document.getElementById('manualInput');
        const kode = input.value.trim();
        if (kode) {
            sendScanData(kode);
        }
    }

    function onScanSuccess(decodedText, decodedResult) {
        if (!isProcessing) {
            sendScanData(decodedText);
        }
    }

    function onScanFailure(error) {
        // Ignore scan failures, keep scanning
    }
    
    // Auto focus input on load
    window.onload = function() {
        document.getElementById('manualInput').focus();
    };
</script>

import { Controller } from '@hotwired/stimulus';
import 'barcode-detector';

export default class extends Controller {
    static targets = ['video', 'startBtn', 'stopBtn', 'status', 'error', 'method'];
    static values = {
        checkUrl: String,
        searchUrl: String,
        saveUrl: String
    }

    connect() {
        this.isScanning = false;
        this.detectionActive = false;
        this.lastScannedISBN = null;
        this.lastScanTime = 0;
        this.scannerStream = null;
        this.barcodeDetector = null;
        this.isProcessing = false;
    }

    disconnect() {
        this.stop();
    }

    async start() {
        if (this.isScanning) return;

        // Vérifier que getUserMedia est disponible
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost';
            if (!isSecure) {
                alert('⚠️ HTTPS requis\n\nLa caméra nécessite une connexion sécurisée (HTTPS) sur mobile.');
                this.errorTarget.textContent = 'HTTPS requis pour utiliser la caméra.';
                return;
            }
            this.errorTarget.textContent = 'Votre navigateur ne supporte pas l\'accès à la caméra.';
            return;
        }

        this.isScanning = true;
        this.detectionActive = true;
        this.toggleButtons(true);
        this.statusTarget.textContent = 'Initialisation...';
        this.errorTarget.textContent = '';

        await this.startNativeScanner();
    }

    stop() {
        this.isScanning = false;
        this.detectionActive = false;
        this.toggleButtons(false);

        // Arrêter le flux vidéo
        if (this.scannerStream) {
            this.scannerStream.getTracks().forEach(track => track.stop());
            this.scannerStream = null;
            this.videoTarget.style.display = 'none';
        }

        this.barcodeDetector = null;
        this.statusTarget.textContent = 'Scan arrêté';
    }

    async startNativeScanner() {
        const isNative = 'BarcodeDetector' in window && window.BarcodeDetector.toString().includes('[native code]');
        this.methodTarget.textContent = isNative
            ? 'Méthode: BarcodeDetector API (native)'
            : 'Méthode: BarcodeDetector API (polyfill)';

        // Supporter plusieurs formats de codes-barres pour les livres
        const formats = [
            'ean_13',      // ISBN-13 (le plus courant)
            'ean_8',       // ISBN-8
            'upc_a',       // Code UPC américain
            'upc_e',       // Code UPC compact
            'code_128',    // Code 128 (parfois utilisé)
            'code_39',     // Code 39
            'code_93',     // Code 93
            'codabar',     // Codabar
            'itf'          // Interleaved 2 of 5
        ];

        this.barcodeDetector = new BarcodeDetector({ formats });

        try {
            // Demander une résolution plus élevée pour une meilleure détection
            this.scannerStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            });
            this.videoTarget.srcObject = this.scannerStream;
            this.videoTarget.style.display = 'block';

            // Attendre que la vidéo soit prête avant de démarrer la détection
            await new Promise((resolve) => {
                this.videoTarget.addEventListener('loadedmetadata', resolve, { once: true });
            });

            // S'assurer que la vidéo est en train de jouer
            await this.videoTarget.play();

            this.statusTarget.textContent = 'Pointez un code-barres vers la caméra';
            this.startDetectionLoop();

        } catch (err) {
            this.handleError(err);
        }
    }

    startDetectionLoop() {
        const detectLoop = async () => {
            if (!this.detectionActive) return;

            try {
                const barcodes = await this.barcodeDetector.detect(this.videoTarget);

                if (barcodes.length > 0) {
                    // Log pour debug : voir tous les codes détectés
                    barcodes.forEach(barcode => {
                        console.log(`📊 Détecté: ${barcode.rawValue} (format: ${barcode.format})`);
                    });

                    if (this.detectionActive && !this.isProcessing) {
                        await this.handleDetectedBarcode(barcodes[0].rawValue);
                    }
                }
            } catch (err) {
                console.error('Detection error:', err);
            }

            if (this.detectionActive) {
                requestAnimationFrame(detectLoop);
            }
        };
        detectLoop();
    }

    async handleDetectedBarcode(rawIsbn) {
        let isbn = rawIsbn.replace(/[-\s]/g, '').replace(/^0+/, '');

        // Validation basique
        if ((!isbn.startsWith('978') && !isbn.startsWith('979')) || isbn.length !== 13) return;

        // Anti-rebond
        const now = Date.now();
        if (isbn === this.lastScannedISBN && (now - this.lastScanTime) < 5000) return;

        this.lastScannedISBN = isbn;
        this.lastScanTime = now;

        // ARRÊTER LE SCAN pendant le traitement
        this.isProcessing = true;
        this.pauseScanning();

        this.statusTarget.textContent = `ISBN détecté: ${isbn}`;
        console.log('🔍 Traitement de l\'ISBN:', isbn);

        try {
            // 1. Check DB
            this.statusTarget.textContent = 'Vérification dans la base de données...';
            const checkRes = await this.postJson(this.checkUrlValue, { isbn });

            if (checkRes.exists) {
                alert(`Ce livre existe déjà !\n"${checkRes.book.title}"`);
                return;
            }

            // 2. Search Google
            this.statusTarget.textContent = 'Recherche sur Google Books...';
            const searchRes = await this.postJson(this.searchUrlValue, { isbn });

            if (searchRes.error) {
                alert('Aucun livre trouvé pour cet ISBN.');
                return;
            }

            // 3. Confirm & Save
            const authors = searchRes.authors?.join(', ') || 'Auteur inconnu';
            const confirmMsg = `Titre: ${searchRes.title}\nAuteur(s): ${authors}\n\nVoulez-vous ajouter ce livre à votre bibliothèque ?`;

            if (confirm(confirmMsg)) {
                this.statusTarget.textContent = 'Ajout du livre...';
                await this.postJson(this.saveUrlValue, { isbn });
                alert('Livre ajouté avec succès !');
                this.statusTarget.textContent = `Ajouté: ${searchRes.title}`;
            } else {
                this.statusTarget.textContent = 'Ajout annulé';
            }

        } catch (e) {
            console.error('Erreur:', e);
            this.errorTarget.textContent = e.message || 'Erreur inconnue';
            alert(`Erreur: ${e.message || 'Erreur inconnue'}`);
        } finally {
            // REPRENDRE LE SCAN après le traitement
            this.isProcessing = false;
            this.resumeScanning();
            console.log('✅ Scan repris');
        }
    }

    pauseScanning() {
        console.log('⏸️  Scan en pause');
        this.detectionActive = false;
    }

    resumeScanning() {
        console.log('▶️  Reprise du scan dans 1 seconde...');
        this.statusTarget.textContent = 'Prêt à scanner...';

        setTimeout(() => {
            this.detectionActive = true;

            // Redémarrer la boucle de détection
            if (this.barcodeDetector && this.videoTarget.srcObject) {
                this.startDetectionLoop();
            }
        }, 1000);
    }

    async postJson(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await response.json();
        if (!response.ok && !json.error) throw new Error(response.statusText);
        return json;
    }

    handleError(err) {
        console.error('Camera error:', err);

        let errorMessage = 'Erreur caméra: ';

        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            errorMessage = '⚠️ Accès à la caméra refusé.\n\n';
            errorMessage += '1. Cliquez sur l\'icône 🔒 ou ⓘ dans la barre d\'adresse\n';
            errorMessage += '2. Autorisez l\'accès à la caméra\n';
            errorMessage += '3. Rechargez la page';
            alert(errorMessage);
            this.errorTarget.textContent = 'Accès caméra refusé. Veuillez autoriser l\'accès.';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            errorMessage = 'Aucune caméra trouvée sur cet appareil.';
            this.errorTarget.textContent = errorMessage;
        } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
            errorMessage = 'La caméra est déjà utilisée par une autre application.';
            this.errorTarget.textContent = errorMessage;
        } else if (err.name === 'NotSupportedError') {
            errorMessage = '⚠️ HTTPS requis pour accéder à la caméra.\n\n';
            errorMessage += 'Cette fonctionnalité nécessite une connexion sécurisée (HTTPS).';
            alert(errorMessage);
            this.errorTarget.textContent = 'HTTPS requis pour la caméra.';
        } else {
            this.errorTarget.textContent = `Erreur caméra: ${err.message || err.name || err}`;
        }

        this.stop();
    }

    toggleButtons(isScanning) {
        this.startBtnTarget.classList.toggle('hidden', isScanning);
        this.stopBtnTarget.classList.toggle('hidden', !isScanning);
    }
}

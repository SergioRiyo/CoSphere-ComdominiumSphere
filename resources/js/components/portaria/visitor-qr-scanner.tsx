import { Camera, CameraOff, RefreshCw, ScanLine, X } from 'lucide-react';
import type QrScanner from 'qr-scanner';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type CameraState = 'requesting' | 'active' | 'detected' | 'error';

type VisitorQrScannerProps = {
    disabled?: boolean;
    hasPreviousResult?: boolean;
    onDetected: (accessCode: string) => void | Promise<void>;
};

export default function VisitorQrScanner({
    disabled = false,
    hasPreviousResult = false,
    onDetected,
}: VisitorQrScannerProps) {
    const [open, setOpen] = useState(false);
    const [restartAttempt, setRestartAttempt] = useState(0);
    const [cameraState, setCameraState] = useState<CameraState>('requesting');
    const [cameraError, setCameraError] = useState<string | null>(null);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const detectionLockedRef = useRef(false);
    const onDetectedRef = useRef(onDetected);

    useEffect(() => {
        onDetectedRef.current = onDetected;
    }, [onDetected]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const video = videoRef.current;

        if (!video) {
            return;
        }

        let disposed = false;
        let scanner: QrScanner | null = null;
        let stream: MediaStream | null = null;
        detectionLockedRef.current = false;
        setCameraState('requesting');
        setCameraError(null);

        const stopCamera = () => {
            const scannerToStop = scanner;
            scanner = null;

            if (scannerToStop) {
                void scannerToStop.pause(true);
                scannerToStop.destroy();
            }

            const streams = new Set<MediaStream>();

            if (stream) {
                streams.add(stream);
            }

            if (video.srcObject instanceof MediaStream) {
                streams.add(video.srcObject);
            }

            for (const stream of streams) {
                for (const track of stream.getTracks()) {
                    track.stop();
                }
            }

            stream = null;
            video.srcObject = null;
        };

        const handleDetection = (scanResult: QrScanner.ScanResult) => {
            if (disposed || detectionLockedRef.current) {
                return;
            }

            detectionLockedRef.current = true;
            setCameraState('detected');
            stopCamera();
            setOpen(false);

            void Promise.resolve()
                .then(() => onDetectedRef.current(scanResult.data))
                .catch(() => undefined);
        };

        const startCamera = async () => {
            if (!window.isSecureContext) {
                setCameraState('error');
                setCameraError(
                    'A câmera exige uma conexão segura. Acesse o CoSphere por HTTPS ou use localhost durante o desenvolvimento.',
                );

                return;
            }

            if (!navigator.mediaDevices?.getUserMedia) {
                setCameraState('error');
                setCameraError(
                    'Este navegador não oferece acesso compatível à câmera.',
                );

                return;
            }

            try {
                const { default: QrScanner } = await import('qr-scanner');

                if (disposed) {
                    return;
                }

                scanner = new QrScanner(video, handleDetection, {
                    preferredCamera: 'environment',
                    maxScansPerSecond: 10,
                    highlightScanRegion: true,
                    highlightCodeOutline: true,
                    returnDetailedScanResult: true,
                    onDecodeError: (error) => {
                        if (disposed || error === QrScanner.NO_QR_CODE_FOUND) {
                            return;
                        }

                        detectionLockedRef.current = true;
                        stopCamera();
                        setCameraState('error');
                        setCameraError(
                            'O navegador não conseguiu processar as imagens da câmera.',
                        );
                    },
                });

                const cameraStream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                    },
                });

                if (disposed) {
                    for (const track of cameraStream.getTracks()) {
                        track.stop();
                    }

                    return;
                }

                stream = cameraStream;
                video.srcObject = cameraStream;

                if (document.hidden) {
                    stopCamera();
                    setCameraState('error');
                    setCameraError(
                        'A câmera não pode ser iniciada com a página em segundo plano. Volte para esta página e tente novamente.',
                    );

                    return;
                }

                await scanner.start();

                if (disposed) {
                    return;
                }

                setCameraState('active');
            } catch (error) {
                if (disposed) {
                    return;
                }

                stopCamera();
                setCameraState('error');
                setCameraError(cameraErrorMessage(error));
            }
        };

        void startCamera();

        return () => {
            disposed = true;
            detectionLockedRef.current = true;
            stopCamera();
        };
    }, [open, restartAttempt]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    size="lg"
                    variant="outline"
                    disabled={disabled}
                    className="w-full"
                >
                    <ScanLine aria-hidden="true" />
                    {hasPreviousResult ? 'Ler outro QR Code' : 'Ler QR Code'}
                </Button>
            </DialogTrigger>

            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto p-4 sm:max-w-xl sm:p-6">
                <DialogHeader className="pr-8">
                    <DialogTitle className="flex items-center gap-2">
                        <Camera className="size-5 text-cosphere-orange" />
                        Leitura de QR Code
                    </DialogTitle>
                    <DialogDescription>
                        Aponte a câmera para o QR apresentado pelo visitante. A
                        entrada não será registrada automaticamente.
                    </DialogDescription>
                </DialogHeader>

                <div
                    className="relative mx-auto aspect-[3/4] max-h-[65dvh] w-full max-w-md overflow-hidden rounded-2xl border bg-black shadow-inner sm:aspect-video sm:max-w-none"
                    aria-live="polite"
                >
                    <video
                        ref={videoRef}
                        muted
                        playsInline
                        aria-label="Visualização da câmera para leitura do QR Code"
                        className="size-full object-cover"
                    />

                    {cameraState === 'requesting' && (
                        <CameraOverlay>
                            <Spinner className="size-7" />
                            <span>Solicitando acesso à câmera...</span>
                        </CameraOverlay>
                    )}

                    {cameraState === 'detected' && (
                        <CameraOverlay>
                            <ScanLine className="size-7" />
                            <span>QR Code detectado. Validando...</span>
                        </CameraOverlay>
                    )}

                    {cameraState === 'error' && (
                        <CameraOverlay>
                            <CameraOff className="size-8" />
                            <span>Câmera indisponível</span>
                        </CameraOverlay>
                    )}
                </div>

                {cameraState === 'active' && (
                    <p className="text-center text-sm text-muted-foreground">
                        Centralize o QR Code na área destacada e mantenha o
                        dispositivo firme.
                    </p>
                )}

                {cameraState === 'error' && cameraError && (
                    <Alert variant="destructive">
                        <CameraOff aria-hidden="true" />
                        <AlertTitle>
                            Não foi possível acessar a câmera
                        </AlertTitle>
                        <AlertDescription>
                            {cameraError} Você pode continuar utilizando o
                            código manual.
                        </AlertDescription>
                    </Alert>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        <X aria-hidden="true" />
                        Fechar
                    </Button>

                    {cameraState === 'error' && (
                        <Button
                            type="button"
                            onClick={() =>
                                setRestartAttempt((attempt) => attempt + 1)
                            }
                        >
                            <RefreshCw aria-hidden="true" />
                            Tentar novamente
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function CameraOverlay({ children }: { children: ReactNode }) {
    return (
        <div className="absolute inset-0 z-10 grid place-content-center place-items-center gap-3 bg-black/75 px-6 text-center text-sm font-medium text-white">
            {children}
        </div>
    );
}

function cameraErrorMessage(error: unknown): string {
    if (error instanceof DOMException) {
        if (
            error.name === 'NotAllowedError' ||
            error.name === 'SecurityError'
        ) {
            return 'A permissão de câmera foi negada ou bloqueada pelo navegador.';
        }

        if (
            error.name === 'NotFoundError' ||
            error.name === 'OverconstrainedError'
        ) {
            return 'Nenhuma câmera compatível foi encontrada neste dispositivo.';
        }

        if (error.name === 'NotReadableError' || error.name === 'AbortError') {
            return 'A câmera está ocupada ou não pôde ser iniciada neste dispositivo.';
        }
    }

    return 'Não foi possível iniciar o leitor de QR Code neste dispositivo.';
}

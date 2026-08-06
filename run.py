import subprocess
import threading
import time
import os
import sys
import psutil

try:
    import msvcrt  # hanya tersedia di Windows
except ImportError:
    msvcrt = None

try:
    import pystray
    from PIL import Image, ImageDraw
except ImportError:
    print(
        "[ERROR] Modul belum lengkap. Jalankan:\n"
        "  pip install pystray pillow psutil\n",
        flush=True,
    )
    sys.exit(1)

# Simpan semua proses yang berjalan (yang di-spawn oleh session ini)
processes = {}
processes_lock = threading.Lock()

# Proses aplikasi yang TIDAK boleh dimatikan (IDE, shell desktop, dll.)
PROTECTED_PROCESS_NAMES = {
    "cursor.exe",
    "code.exe",
    "code - insiders.exe",
    "devenv.exe",
    "explorer.exe",
    "windowsterminal.exe",
    "wt.exe",
    "searchhost.exe",
    "shellexperiencehost.exe",
    "startmenuexperiencehost.exe",
    "applicationframehost.exe",
    "systemsettings.exe",
    "taskmgr.exe",
    "dwm.exe",
}

PROGRAMPRINTING_DIR = r"D:\laragon\www\ProgramPrinting"
SSL_CERT_DIR = os.path.join(PROGRAMPRINTING_DIR, "scripts", "dev-https", "certs")
SSL_GENERATE_SCRIPT = os.path.join(
    PROGRAMPRINTING_DIR, "scripts", "dev-https", "generate-cert.ps1"
)

# Absensi Online (Laravel + Vite via npm run dev:watch)
ABSENSI_ONLINE_DIR = r"D:\Project\ABSENSI ONLINE\webapp"
ABSENSI_ONLINE_PORT = 8008


def kill_existing(name):
    """Matikan proses lama (yang ditrack session ini) jika masih berjalan"""
    with processes_lock:
        if name in processes:
            proc = processes[name]
            if proc and proc.poll() is None:  # Masih berjalan
                try:
                    parent = psutil.Process(proc.pid)
                    for child in parent.children(recursive=True):
                        child.kill()
                    parent.kill()
                    print(f"[STOP] {name} dihentikan (PID: {proc.pid})", flush=True)
                except Exception as e:
                    print(f"[WARN] Gagal hentikan {name}: {e}", flush=True)
            del processes[name]


def _cmdline_str(proc_info):
    return " ".join(proc_info.get("cmdline") or []).lower()


def _is_protected_process(proc_info):
    return (proc_info.get("name") or "").lower() in PROTECTED_PROCESS_NAMES


def _get_service_type(proc_info):
    """Kembalikan tipe service jika proses ini dev-service yang dikelola, else None."""
    if _is_protected_process(proc_info):
        return None

    cmdline = _cmdline_str(proc_info)
    cwd = (proc_info.get("cwd") or "").lower()
    text = f"{cmdline} {cwd}"

    npm_dev_indicators = ("npm", "vite", "webpack", "mix", "node_modules", "nodemon")

    if "programprinting" in text:
        if any(
            x in cmdline
            for x in (
                "artisan serve",
                "reverb:start",
                "artisan reverb",
                "proxy.mjs",
                "npm run",
            )
        ):
            return "programprinting"

    if "printercrm" in text:
        if any(x in cmdline for x in npm_dev_indicators):
            return "printercrm"

    if "playstationbilling" in text and "backend_playstationbilling" not in text:
        if any(x in cmdline for x in npm_dev_indicators):
            return "playstationbilling"

    if "backend_playstationbilling" in text:
        if "server.js" in cmdline:
            return "backend_playstationbilling"

    if "api_printing" in text:
        if "agent.py" in cmdline:
            return "api_printing"

    # Absensi Online: path project atau port serve 8008
    if (
        "absensi online" in text
        or "absensi-online" in text
        or r"\project\absensi online" in text
        or "/project/absensi online" in text
    ):
        if any(
            x in cmdline
            for x in (
                "artisan serve",
                "npm run",
                "dev:watch",
                "nodemon",
                "vite",
            )
        ):
            return "absensi_online"

    if "artisan serve" in cmdline and (
        f"--port={ABSENSI_ONLINE_PORT}" in cmdline
        or f"--port {ABSENSI_ONLINE_PORT}" in cmdline
        or f":{ABSENSI_ONLINE_PORT}" in cmdline
    ):
        return "absensi_online"

    return None


def _is_managed_service(proc_info, service_type=None):
    st = _get_service_type(proc_info)
    if st is None:
        return False
    if service_type and st != service_type:
        return False
    return True


def find_and_kill_orphan_services(service_type=None):
    """
    Scan proses di sistem dan matikan hanya dev-service orphan
    (bukan aplikasi seperti Cursor/Code).
    """
    killed = []
    for proc in psutil.process_iter(["pid", "name", "cmdline", "cwd"]):
        try:
            if not _is_managed_service(proc.info, service_type):
                continue
            cmdline = " ".join(proc.info.get("cmdline") or [])
            proc.kill()
            killed.append((proc.info.get("pid"), cmdline or proc.info.get("name")))
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue
    return killed


def kill_port_listeners(ports):
    """Matikan proses yang LISTEN di port tertentu (Windows-friendly via psutil)."""
    killed = []
    for conn in psutil.net_connections(kind="inet"):
        try:
            if conn.status != psutil.CONN_LISTEN:
                continue
            if not conn.laddr or conn.laddr.port not in ports:
                continue
            if not conn.pid:
                continue
            p = psutil.Process(conn.pid)
            info = p.as_dict(attrs=["pid", "name", "cmdline", "cwd"])
            if _is_protected_process(info):
                continue
            for child in p.children(recursive=True):
                child.kill()
            p.kill()
            killed.append((conn.pid, conn.laddr.port))
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue
    return killed


def start_process(name, command, cwd):
    """Hentikan jika masih jalan (tracked), lalu jalankan ulang"""
    kill_existing(name)

    try:
        proc = subprocess.Popen(
            command,
            cwd=cwd,
            shell=True,
            creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == "win32" else 0,
        )
        with processes_lock:
            processes[name] = proc
        print(f"[OK] {name} berhasil dijalankan (PID: {proc.pid})", flush=True)
        return proc
    except Exception as e:
        print(f"[ERROR] {name} gagal dijalankan: {e}", flush=True)
        return None


def ensure_programprinting_ssl_cert():
    """Buat sertifikat self-signed sekali jika belum ada."""
    cert_file = os.path.join(SSL_CERT_DIR, "server.crt")
    key_file = os.path.join(SSL_CERT_DIR, "server.key")

    if os.path.isfile(cert_file) and os.path.isfile(key_file):
        return

    print("[SSL] Sertifikat belum ada, membuat sertifikat...", flush=True)
    try:
        subprocess.run(
            ["powershell", "-File", SSL_GENERATE_SCRIPT],
            check=True,
        )
        print("[SSL] Sertifikat berhasil dibuat.", flush=True)
    except Exception as e:
        print(f"[ERROR] Gagal membuat sertifikat SSL: {e}", flush=True)
        raise


def run_npm_dev_printercrm():
    start_process(
        "NPM Dev PrinterCRM",
        ["npm", "run", "dev"],
        r"D:\laragon\www\PrinterCRM",
    )


def run_npm_dev_playstationbilling():
    start_process(
        "NPM Dev PlaystationBilling",
        ["npm", "run", "dev"],
        r"D:\laragon\www\PlaystationBilling",
    )


def run_node_backend_playstationbilling():
    start_process(
        "Node Backend PlaystationBilling",
        ["node", "server.js"],
        r"D:\laragon\www\BackEnd_PlayStationBilling",
    )


def run_laravel_programprinting():
    ensure_programprinting_ssl_cert()

    # HTTP internal (port 8001) — tidak diakses langsung dari browser
    start_process(
        "Laravel ProgramPrinting Serve",
        ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8001"],
        PROGRAMPRINTING_DIR,
    )

    time.sleep(2)  # tunggu artisan siap sebelum proxy HTTPS start

    # HTTPS (port 8000) — diakses browser/HP untuk webcam dll.
    start_process(
        "Laravel ProgramPrinting HTTPS",
        ["node", "scripts/dev-https/proxy.mjs"],
        PROGRAMPRINTING_DIR,
    )

    start_process(
        "Laravel ProgramPrinting Reverb",
        ["php", "artisan", "reverb:start"],
        PROGRAMPRINTING_DIR,
    )
    start_process(
        "Laravel ProgramPrinting Build",
        ["npm", "run", "prod"],
        PROGRAMPRINTING_DIR,
    )


def run_python_agent():
    start_process(
        "Python Agent",
        ["python", "agent.py"],
        r"D:\Api_Printing",
    )


def run_absensi_online():
    """
    ABSENSI ONLINE — setara start-server.bat:
    npm run dev:watch = nodemon (php artisan serve :8008) + vite hot-reload
    """
    if not os.path.isdir(ABSENSI_ONLINE_DIR):
        print(f"[ERROR] Folder Absensi Online tidak ditemukan: {ABSENSI_ONLINE_DIR}", flush=True)
        return

    # Bebaskan port lama sebelum start
    killed_ports = kill_port_listeners({ABSENSI_ONLINE_PORT, 5173})
    for pid, port in killed_ports:
        print(f"[STOP] Port {port} PID {pid} dibebaskan", flush=True)

    start_process(
        "Absensi Online Dev",
        ["npm", "run", "dev:watch"],
        ABSENSI_ONLINE_DIR,
    )
    print(f"[INFO] Absensi Online -> http://localhost:{ABSENSI_ONLINE_PORT}", flush=True)


SERVICES = {
    "1": {
        "label": "Laravel ProgramPrinting",
        "type": "programprinting",
        "run": None,
        "tracked_names": [
            "Laravel ProgramPrinting Serve",
            "Laravel ProgramPrinting HTTPS",
            "Laravel ProgramPrinting Reverb",
            "Laravel ProgramPrinting Build",
        ],
    },
    "2": {
        "label": "PrinterCRM",
        "type": "printercrm",
        "run": None,
        "tracked_names": ["NPM Dev PrinterCRM"],
    },
    "3": {
        "label": "PlaystationBilling",
        "type": "playstationbilling",
        "run": None,
        "tracked_names": ["NPM Dev PlaystationBilling"],
    },
    "4": {
        "label": "Backend PlaystationBilling",
        "type": "backend_playstationbilling",
        "run": None,
        "tracked_names": ["Node Backend PlaystationBilling"],
    },
    "5": {
        "label": "Python Agent",
        "type": "api_printing",
        "run": None,
        "tracked_names": ["Python Agent"],
    },
    "6": {
        "label": "Absensi Online",
        "type": "absensi_online",
        "run": None,
        "tracked_names": ["Absensi Online Dev"],
    },
}

SERVICES["1"]["run"] = run_laravel_programprinting
SERVICES["2"]["run"] = run_npm_dev_printercrm
SERVICES["3"]["run"] = run_npm_dev_playstationbilling
SERVICES["4"]["run"] = run_node_backend_playstationbilling
SERVICES["5"]["run"] = run_python_agent
SERVICES["6"]["run"] = run_absensi_online

ALL_KEY = "7"


def stop_service(service_key):
    """Hentikan satu service (tracked + orphan). Key ALL_KEY = semua."""
    if service_key == ALL_KEY:
        stop_all_system()
        return

    svc = SERVICES.get(service_key)
    if not svc:
        print(f"[WARN] Service tidak dikenal: {service_key}", flush=True)
        return

    print(f"[STOP] Menghentikan {svc['label']}...", flush=True)
    for name in svc["tracked_names"]:
        kill_existing(name)

    if svc["type"] == "absensi_online":
        kill_port_listeners({ABSENSI_ONLINE_PORT, 5173})

    killed = find_and_kill_orphan_services(svc["type"])
    for pid, cmd in killed:
        print(f"[STOP] Killed PID {pid}: {cmd}", flush=True)
    if not killed:
        print(f"[STOP] Tidak ada proses orphan untuk {svc['label']}.", flush=True)


def restart_service(service_key):
    """Stop lalu jalankan ulang service terpilih. Key ALL_KEY = semua."""
    if service_key == ALL_KEY:
        stop_all_system()
        restart_all()
        return

    svc = SERVICES.get(service_key)
    if not svc:
        print(f"[WARN] Service tidak dikenal: {service_key}", flush=True)
        return

    print(f"[RESTART] Merestart {svc['label']}...", flush=True)
    stop_service(service_key)
    threading.Thread(target=svc["run"], daemon=True).start()


def restart_all():
    """Restart semua service (tracked session ini)"""
    print("[RESTART] Merestart semua service...", flush=True)
    threading.Thread(target=run_npm_dev_printercrm, daemon=True).start()
    threading.Thread(target=run_npm_dev_playstationbilling, daemon=True).start()
    threading.Thread(target=run_node_backend_playstationbilling, daemon=True).start()
    threading.Thread(target=run_laravel_programprinting, daemon=True).start()
    threading.Thread(target=run_python_agent, daemon=True).start()
    threading.Thread(target=run_absensi_online, daemon=True).start()


def stop_all():
    """Hentikan semua service yang ditrack session ini"""
    print("[STOP] Menghentikan semua service (tracked)...", flush=True)
    names = list(processes.keys())
    for name in names:
        kill_existing(name)
    print("[STOP] Semua service (tracked) dihentikan.", flush=True)


def stop_all_system():
    """
    Hentikan semua dev-service, baik yang ditrack session ini maupun proses
    orphan di sistem. Aplikasi seperti Cursor tidak disentuh.
    """
    stop_all()
    kill_port_listeners({ABSENSI_ONLINE_PORT, 5173})
    print("[STOP] Mencari dev-service orphan di sistem...", flush=True)
    killed = find_and_kill_orphan_services()
    if killed:
        for pid, cmd in killed:
            print(f"[STOP] Killed PID {pid}: {cmd}", flush=True)
    else:
        print("[STOP] Tidak ada proses orphan yang ditemukan.", flush=True)


def make_restart_handler(service_key):
    def handler(icon, item):
        threading.Thread(target=lambda: restart_service(service_key), daemon=True).start()

    return handler


def on_quit(icon, item):
    stop_all_system()
    icon.stop()


def create_image():
    img = Image.new("RGB", (64, 64), "blue")
    d = ImageDraw.Draw(img)
    d.rectangle([16, 16, 48, 48], fill="white")
    return img


def input_with_timeout(timeout=30, default="1", options_hint="1/2/3"):
    """
    Tunggu input user maksimal `timeout` detik. Kalau tidak ada input
    sampai waktu habis, otomatis kembalikan `default`.
    """
    if msvcrt:
        start_time = time.time()
        chars = []
        while True:
            remaining = timeout - (time.time() - start_time)
            if remaining <= 0:
                print(
                    f"\r[TIMEOUT] Tidak ada input, menggunakan default: {default}"
                    + " " * 20,
                    flush=True,
                )
                return default
            print(
                f"\rPilih opsi [{options_hint}] (default {default} dalam {int(remaining) + 1:>2}s): {''.join(chars)}",
                end="",
                flush=True,
            )
            if msvcrt.kbhit():
                ch = msvcrt.getwch()
                if ch in ("\r", "\n"):
                    print()
                    return "".join(chars).strip() or default
                elif ch == "\b":
                    chars = chars[:-1]
                else:
                    chars.append(ch)
            time.sleep(0.1)
    else:
        result = [None]

        def get_input():
            try:
                result[0] = input(
                    f"Pilih opsi [{options_hint}] (default {default} dalam {timeout}s): "
                )
            except Exception:
                pass

        t = threading.Thread(target=get_input, daemon=True)
        t.start()
        t.join(timeout)
        if t.is_alive():
            print(
                f"\n[TIMEOUT] Tidak ada input dalam {timeout}s, menggunakan default: {default}",
                flush=True,
            )
            return default
        return (result[0] or "").strip() or default


def show_restart_menu():
    """Pilih service mana yang akan di-restart."""
    print("\n=== Pilih Service untuk Restart ===")
    print("1. Laravel ProgramPrinting")
    print("2. PrinterCRM")
    print("3. PlaystationBilling")
    print("4. Backend PlaystationBilling")
    print("5. Python Agent")
    print("6. Absensi Online")
    print("7. All")
    return input_with_timeout(timeout=30, default=ALL_KEY, options_hint="1-7")


def show_startup_menu():
    """Tampilkan opsi sebelum service dijalankan, dengan timeout 30s ke default"""
    print("=== Server Monitoring Launcher ===")
    print("1. Run (normal)     - jalankan semua service")
    print("2. Restart          - matikan proses lama (termasuk orphan), lalu jalankan ulang")
    print("3. Stop Service     - matikan semua service yang berjalan, lalu keluar")
    return input_with_timeout(timeout=30, default="1")


if __name__ == "__main__":
    choice = show_startup_menu()

    if choice == "3":
        stop_all_system()
        print("[DONE] Semua service dihentikan. Keluar.", flush=True)
        sys.exit(0)
    elif choice == "2":
        restart_choice = show_restart_menu()
        restart_service(restart_choice)
    else:
        stop_all_system()
        restart_all()

    icon = pystray.Icon(
        "NPM Dev Background",
        create_image(),
        "Server Monitoring",
        menu=pystray.Menu(
            pystray.MenuItem(
                "Restart",
                pystray.Menu(
                    pystray.MenuItem("Laravel ProgramPrinting", make_restart_handler("1")),
                    pystray.MenuItem("PrinterCRM", make_restart_handler("2")),
                    pystray.MenuItem("PlaystationBilling", make_restart_handler("3")),
                    pystray.MenuItem(
                        "Backend PlaystationBilling", make_restart_handler("4")
                    ),
                    pystray.MenuItem("Python Agent", make_restart_handler("5")),
                    pystray.MenuItem("Absensi Online", make_restart_handler("6")),
                    pystray.MenuItem("All", make_restart_handler(ALL_KEY)),
                ),
            ),
            pystray.MenuItem("Quit", on_quit),
        ),
    )
    icon.run()

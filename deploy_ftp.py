import ftplib
import os
import sys

FTP_HOST = "115.68.168.215"
FTP_USER = "nuriohga"
FTP_PASS = "seungho0409#"
LOCAL_DIR = os.path.dirname(os.path.abspath(__file__))
REMOTE_SUBDIR = "/public_html/expense"

print(f"Connecting to FTP {FTP_HOST}...")

try:
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    print("FTP Login Successful!")

    def upload_dir(local_path, remote_path):
        # 🔒 안전 검증: 반드시 /public_html/expense 하위 경로에만 업로드 허용!
        if not remote_path.startswith("/public_html/expense"):
            raise ValueError(f"CRITICAL SAFETY WARNING: Upload target '{remote_path}' is outside /public_html/expense!")

        try:
            ftp.cwd(remote_path)
        except Exception:
            ftp.mkd(remote_path)
            ftp.cwd(remote_path)

        print(f"FTP cwd: {ftp.pwd()}")
        for item in os.listdir(local_path):
            if item in ['.git', 'node_modules', '.gemini', 'scratch', '__pycache__']:
                continue
            
            l_path = os.path.join(local_path, item)
            if os.path.isfile(l_path):
                with open(l_path, 'rb') as f:
                    print(f"Uploading [{remote_path}]: {item}")
                    ftp.storbinary(f'STOR {item}', f)
            elif os.path.isdir(l_path):
                sub_remote = f"{remote_path}/{item}"
                upload_dir(l_path, sub_remote)

    # 1. /public_html/expense 전체 업로드
    upload_dir(LOCAL_DIR, REMOTE_SUBDIR)

    # 2. public/ 내부 파일들을 /public_html/expense 루트에도 정확히 덮어쓰기!
    public_dir = os.path.join(LOCAL_DIR, 'public')
    if os.path.exists(public_dir):
        ftp.cwd(REMOTE_SUBDIR)
        print(f"OVERWRITING EXPENSE ROOT FILES: {ftp.pwd()}")
        for item in os.listdir(public_dir):
            l_path = os.path.join(public_dir, item)
            if os.path.isfile(l_path):
                with open(l_path, 'rb') as f:
                    print(f"OVERWRITING ROOT FILE: {item}")
                    ftp.storbinary(f'STOR {item}', f)

    ftp.quit()
    print("ALL FILES OVERWRITTEN AND DEPLOYED SUCCESSFULLY TO PUBLIC_HTML/EXPENSE!")

except Exception as e:
    print(f"FTP Upload Error: {e}")
    sys.exit(1)

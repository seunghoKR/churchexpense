import ftplib
import os
import sys

FTP_HOST = "115.68.168.215"
FTP_USER = "nuriohga"
FTP_PASS = "seungho0409#"
LOCAL_DIR = r"y:\SynologyDrive\00.withAI\지출요청서"
REMOTE_SUBDIR = "expense"

print(f"Connecting to FTP {FTP_HOST}...")

try:
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    print("FTP Login Successful!")

    print("FTP Directory List:")
    ftp.retrlines('LIST')

    # 하위 디렉터리 expense 생성/이동
    try:
        ftp.cwd(REMOTE_SUBDIR)
    except Exception:
        print(f"Creating remote directory: {REMOTE_SUBDIR}")
        ftp.mkd(REMOTE_SUBDIR)
        ftp.cwd(REMOTE_SUBDIR)

    def upload_dir(local_path, remote_prefix=""):
        for item in os.listdir(local_path):
            # .git, vendor, node_modules 등 제외
            if item in ['.git', 'node_modules', '.gemini', 'scratch']:
                continue
            
            l_path = os.path.join(local_path, item)
            if os.path.isfile(l_path):
                r_filename = item
                with open(l_path, 'rb') as f:
                    print(f"Uploading: {l_path} -> {r_filename}")
                    ftp.storbinary(f'STOR {r_filename}', f)
            elif os.path.isdir(l_path):
                print(f"Creating & entering remote dir: {item}")
                try:
                    ftp.mkd(item)
                except Exception:
                    pass
                ftp.cwd(item)
                upload_dir(l_path, item)
                ftp.cwd("..")

    # 소스 파일 업로드 진행
    upload_dir(LOCAL_DIR)

    # public 폴더의 내용물을 expense 루트에도 올려서 접근 편의성 증대
    public_dir = os.path.join(LOCAL_DIR, 'public')
    if os.path.exists(public_dir):
        print("Uploading public files to root of expense...")
        for item in os.listdir(public_dir):
            l_path = os.path.join(public_dir, item)
            if os.path.isfile(l_path):
                with open(l_path, 'rb') as f:
                    print(f"Uploading public item: {item}")
                    ftp.storbinary(f'STOR {item}', f)

    ftp.quit()
    print("🎉 All files uploaded via FTP successfully!")

except Exception as e:
    print(f"❌ FTP Upload Error: {e}")
    sys.exit(1)

import ftplib

FTP_HOST = "115.68.168.215"
FTP_USER = "nuriohga"
FTP_PASS = "seungho0409#"

ftp = ftplib.FTP(FTP_HOST)
ftp.login(FTP_USER, FTP_PASS)

print("--- PUBLIC_HTML/EXPENSE LIST ---")
try:
    ftp.cwd('public_html/expense')
    ftp.retrlines('LIST')
except Exception as e:
    print("expense err:", e)

ftp.quit()

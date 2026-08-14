import urllib.request

url = "https://expense.sjsnr.kr/index.html"
req = urllib.request.Request(url, headers={
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'Cache-Control': 'no-cache',
    'Pragma': 'no-cache'
})

try:
    with urllib.request.urlopen(req) as resp:
        content = resp.read().decode('utf-8')
        print(f"HTTP Status: {resp.status}")
        print(f"Content Length: {len(content)}")
        print("Contains my-title-select:", "my-title-select" in content)
        print("Contains my-dept-select:", "my-dept-select" in content)
        print("Contains my-mode-select:", "my-mode-select" in content)
        print("Contains toggleTitleSection:", "toggleTitleSection" in content)
        print("Contains 승인 요청 목록:", "승인 요청 목록" in content)
        print("Contains nuriohga@gmail.com:", "nuriohga@gmail.com" in content)
except Exception as e:
    print("Error:", e)

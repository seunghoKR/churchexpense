import urllib.request
import json

base = "https://expense.sjsnr.kr/api/request_action.php"

def test_api(action, query=""):
    url = f"{base}?action={action}&{query}"
    req = urllib.request.Request(url, headers={
        'User-Agent': 'Mozilla/5.0',
        'Cache-Control': 'no-cache'
    })
    try:
        with urllib.request.urlopen(req) as resp:
            data = resp.read().decode('utf-8')
            print(f"=== API: {action} ({query}) ===")
            print(data[:500])
            print()
    except Exception as e:
        print(f"Error testing {action}: {e}")

test_api("get_approved_users")
test_api("get_pending_users")
test_api("check_user_status", "email=nuriohga@gmail.com")
test_api("check_user_status", "email=kakao_5035521659@kakao.com")
test_api("get_social_links", "email=nuriohga@gmail.com")

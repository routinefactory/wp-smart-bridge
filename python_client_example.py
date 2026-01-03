"""
WP Smart Bridge API 클라이언트 예시
단축 링크 생성을 위한 Python 코드

요구사항: pip install requests
"""

import hashlib
import hmac
import time
import json
import requests


class SmartBridgeClient:
    """WP Smart Bridge API 클라이언트"""
    
    # 필수: User-Agent (서버에서 이 값으로 검증함)
    USER_AGENT = "SB-Client/Win64-v2.0"
    
    def __init__(self, site_url: str, api_key: str, secret_key: str):
        """
        Args:
            site_url: 워드프레스 사이트 URL (예: https://example.com)
            api_key: 발급받은 API Key
            secret_key: 발급받은 Secret Key
        """
        self.site_url = site_url.rstrip('/')
        self.api_key = api_key
        self.secret_key = secret_key
        self.api_endpoint = f"{self.site_url}/wp-json/sb/v1/links"
    
    def _generate_signature(self, body: str, timestamp: int) -> str:
        """
        HMAC-SHA256 서명 생성
        
        공식: HMAC_SHA256(Body + Timestamp, SecretKey) → Hex String
        """
        payload = body + str(timestamp)
        signature = hmac.new(
            key=self.secret_key.encode('utf-8'),
            msg=payload.encode('utf-8'),
            digestmod=hashlib.sha256
        ).hexdigest()
        return signature
    
    def create_short_link(self, target_url: str, slug: str = None, loading_message: str = None) -> dict:
        """
        단축 링크 생성
        
        Args:
            target_url: 타겟 URL (예: https://coupa.ng/cfvjXy)
            slug: 커스텀 slug (선택, 없으면 자동 생성)
            loading_message: 로딩 메시지 (선택)
        
        Returns:
            dict: API 응답
        """
        # 1. 요청 바디 준비
        body_data = {
            "target_url": target_url
        }
        if slug:
            body_data["slug"] = slug
        if loading_message:
            body_data["loading_message"] = loading_message
        
        body_json = json.dumps(body_data, separators=(',', ':'))  # 공백 없이
        
        # 2. 타임스탬프 (현재 Unix timestamp)
        timestamp = int(time.time())
        
        # 3. HMAC 서명 생성
        signature = self._generate_signature(body_json, timestamp)
        
        # 4. 헤더 구성
        headers = {
            "Content-Type": "application/json",
            "User-Agent": self.USER_AGENT,
            "X-SB-API-Key": self.api_key,
            "X-SB-Timestamp": str(timestamp),
            "X-SB-Signature": signature,
        }
        
        # 5. API 요청
        response = requests.post(
            self.api_endpoint,
            headers=headers,
            data=body_json,
            timeout=30
        )
        
        return response.json()


# ============================================================
# 사용 예시
# ============================================================

if __name__ == "__main__":
    # 설정 (워드프레스에서 발급받은 값으로 변경)
    SITE_URL = "https://your-wordpress-site.com"
    API_KEY = "sb_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
    SECRET_KEY = "sbs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
    
    # 클라이언트 생성
    client = SmartBridgeClient(SITE_URL, API_KEY, SECRET_KEY)
    
    # 단축 링크 생성
    result = client.create_short_link(
        target_url="https://link.coupang.com/a/cfvjXy",
        # slug="my-custom-slug",  # 선택: 커스텀 slug
        # loading_message="잠시만 기다려주세요..."  # 선택: 로딩 메시지
    )
    
    print(json.dumps(result, indent=2, ensure_ascii=False))


# ============================================================
# 예상 응답값
# ============================================================
"""
✅ 성공 시 (200 OK):
{
  "success": true,
  "short_link": "https://your-site.com/go/aB3xY9",
  "slug": "aB3xY9",
  "target_url": "https://link.coupang.com/a/cfvjXy",
  "platform": "Coupang",
  "created_at": "2026-01-03T23:30:00+09:00"
}

❌ 타임스탬프 만료 (401 Unauthorized):
{
  "code": "expired",
  "message": "Request expired. Timestamp difference exceeds 60 seconds.",
  "data": {"status": 401}
}

❌ User-Agent 불일치 (403 Forbidden):
{
  "code": "forbidden",
  "message": "Unauthorized client. Invalid User-Agent.",
  "data": {"status": 403}
}

❌ HMAC 서명 불일치 (403 Forbidden):
{
  "code": "invalid_signature",
  "message": "HMAC signature verification failed.",
  "data": {"status": 403}
}

❌ API Key 없음/비활성 (403 Forbidden):
{
  "code": "invalid_key",
  "message": "Invalid API key. Key not found or inactive.",
  "data": {"status": 403}
}

❌ Slug 중복 (409 Conflict):
{
  "code": "conflict",
  "message": "Slug already exists.",
  "data": {"status": 409}
}

❌ Slug 생성 실패 (500 Internal Server Error):
{
  "code": "generation_failed",
  "message": "Failed to generate unique slug after 3 retries.",
  "data": {"status": 500}
}
"""

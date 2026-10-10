"""ตัวอย่าง: หลังจาก Python คำนวณ Burnout Risk แล้ว ส่งผลเข้าเว็บ"""
import requests

result = {
    "id": "E001",
    "name": "Amika Yodsaeng",
    "department": "BNK48 Trainee",
    "arrival_time": "08:42",
    "risk": 80,
    "heart_rate": 90,
    "facial": "Burnout 90%",
    "questions": [
        {"id": "Q.1", "text": "ฉันรู้สึกเหนื่อยล้า หมดพลัง ทั้งพลังทางกายและอารมณ์", "answer": "HIGH"},
        {"id": "Q.2", "text": "ฉันรู้สึกเฉยเมยหรือเห็นอกเห็นใจกับผู้คนน้อยลงอย่างไม่สมเหตุผล", "answer": "HIGH"},
        {"id": "Q.3", "text": "ฉันรู้สึกในทางลบกับงานในหน้าที่ที่ทำ", "answer": "Moderate"}
    ]
}

requests.post("http://127.0.0.1:5000/api/update", json=result, timeout=3)
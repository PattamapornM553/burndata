import mysql.connector
from datetime import datetime
import random
import time

# --- แก้เลข IP ตรงนี้ให้เป็นของเครื่อง A (เซิร์ฟเวอร์) ---
server_ip = "10.26.1.95" 
# -----------------------------------------------

try:
    print(f"⏳ กำลังจำลองการสแกน 5 รอบ เพื่อคำนวณผลเข้าตาราง summary_results...")
    
    conn = mysql.connector.connect(
        host=server_ip,
        user="admin_user",
        password="123456",
        database="my_database"
    )
    # ใช้ dictionary=True เพื่อให้เรียกชื่อคอลัมน์ตอนคำนวณได้
    cursor = conn.cursor(dictionary=True)

    test_rfid = "3AB9224A"  # รหัสที่ลงทะเบียนชื่อพี่แพรไว้
    
    # 1. วนลูปจำลองสแกนและบันทึกเข้า user_logs 5 ครั้ง
    for i in range(1, 6):
        test_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        cam_state = random.choice([1, 2])
        heart_rate = round(random.uniform(75.0, 95.0), 2)
        survey = round(random.uniform(70.0, 100.0), 2)

        cursor.execute("""
            INSERT INTO user_logs (recorded_time, rfid_uid, camera_state, heart_rate, survey_answer) 
            VALUES (%s, %s, %s, %s, %s)
        """, (test_time, test_rfid, cam_state, heart_rate, survey))
        conn.commit()
        print(f"   📥 รอบที่ {i}/5: ส่งค่าเข้า user_logs สำเร็จ")
        time.sleep(0.5) # หน่วงเวลาเล็กน้อยให้ดูเป็นธรรมชาติ

    # 2. ดึงข้อมูล 5 รอบล่าสุดมาคำนวณ
    print(f"\n⚙️ ครบ 5 รอบ! กำลังคำนวณคะแนน Final Score...")
    cursor.execute("SELECT * FROM user_logs WHERE rfid_uid = %s ORDER BY id DESC LIMIT 5", (test_rfid,))
    logs = cursor.fetchall()
    
    sum_hr = 0
    sum_survey = 0
    sum_cam = 0
    
    for row in logs:
        sum_hr += row['heart_rate']
        sum_survey += row['survey_answer']
        sum_cam += 100 if row['camera_state'] == 1 else 50
        
    final_score = ((sum_hr / 5) * 0.4) + ((sum_survey / 5) * 0.4) + ((sum_cam / 5) * 0.2)
    
    # 3. บันทึกผลลัพธ์ลง summary_results
    recorded_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cursor.execute("""
        INSERT INTO summary_results (rfid_uid, final_score, created_at) 
        VALUES (%s, %s, %s)
    """, (test_rfid, round(final_score, 2), recorded_time))
    conn.commit()

    print(f"✅ คำนวณสำเร็จ! บันทึกคะแนนรวม ({final_score:.2f}) ลงตาราง summary_results เรียบร้อย!")
    print("👉 เปิดหน้าเว็บ dashboard.php ดูได้เลยครับ คะแนนของรหัสนี้จะขึ้นโชว์ในตารางทันที")

except mysql.connector.Error as err:
    print(f"❌ Error: {err}")
finally:
    if 'conn' in locals() and conn.is_connected():
        cursor.close()
        conn.close()
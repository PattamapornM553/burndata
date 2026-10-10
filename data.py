import mysql.connector
import random
from datetime import datetime

def connect_db():
    return mysql.connector.connect(host="localhost", user="root", password="", database="my_database")

print("▶️ เริ่มระบบอ่าน RFID (พิมพ์รหัสจำลอง แล้วกด Enter)")

while True:
    scanned_rfid = input("\n[รอรับบัตร] กรุณาแตะบัตร: ")
    
    try:
        conn = connect_db()
        cursor = conn.cursor(dictionary=True)

        # ------------------------------------------------
        # ส่วนที่ 1: ตรวจสอบสิทธิ์ (Whitelist & Admin Register)
        # ------------------------------------------------
        cursor.execute("SELECT username FROM registered_users WHERE rfid_uid = %s", (scanned_rfid,))
        user = cursor.fetchone()

        if not user:
            # ถ้าเป็นบัตรใหม่ (ไม่มีในระบบ) -> อัปเดตส่งให้หน้าเว็บแอดมิน
            print(f"⚠️ ไม่พบรหัส [{scanned_rfid}] ในระบบ")
            print(">> กำลังส่งรหัสไปที่หน้า Admin เพื่อรอการลงทะเบียน...")
            
            cursor.execute("UPDATE temp_rfid SET rfid_uid = %s WHERE id = 1", (scanned_rfid,))
            conn.commit()
            continue  # ข้ามการบันทึกข้อมูล แล้ววนกลับไปรอบัตรใบใหม่

        # ------------------------------------------------
        # ส่วนที่ 2: ถ้ามีชื่อในระบบ -> บันทึกข้อมูลและคำนวณ
        # ------------------------------------------------
        username = user['username']
        print(f"👤 ตรวจพบผู้ใช้: {username} (กำลังบันทึกข้อมูล...)")
        
        camera_state = random.choice([1, 2])
        heart_rate = round(random.uniform(70.0, 110.0), 2)
        survey_answer = round(random.uniform(50.0, 100.0), 2)
        recorded_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        # บันทึกข้อมูลรายรอบ
        cursor.execute("""INSERT INTO user_logs (recorded_time, rfid_uid, camera_state, heart_rate, survey_answer) 
                          VALUES (%s, %s, %s, %s, %s)""", 
                       (recorded_time, scanned_rfid, camera_state, heart_rate, survey_answer))
        conn.commit()
        
        # นับรอบสะสม
        cursor.execute("SELECT COUNT(*) AS total FROM user_logs WHERE rfid_uid = %s", (scanned_rfid,))
        total_scan = cursor.fetchone()['total']
        print(f"✅ บันทึกสำเร็จ! (สะสมได้ {total_scan} รอบ)")
        
        # ถ้าครบ 5 รอบ ให้คำนวณ
        if total_scan > 0 and total_scan % 5 == 0:
            print(f"🎉 สะสมครบ 5 รอบ กำลังคำนวณ Final Score...")
            
            cursor.execute("SELECT * FROM user_logs WHERE rfid_uid = %s ORDER BY id DESC LIMIT 5", (scanned_rfid,))
            logs = cursor.fetchall()
            
            sum_hr, sum_survey, sum_cam = 0, 0, 0
            for row in logs:
                sum_hr += row['heart_rate']
                sum_survey += row['survey_answer']
                sum_cam += 100 if row['camera_state'] == 1 else 50
                
            avg_hr = sum_hr / 5
            avg_survey = sum_survey / 5
            avg_cam = sum_cam / 5
            
            final_score = (avg_hr * 0.4) + (avg_survey * 0.4) + (avg_cam * 0.2)
            
            cursor.execute("""INSERT INTO summary_results (rfid_uid, final_score, created_at) 
                              VALUES (%s, %s, %s)""", 
                           (scanned_rfid, round(final_score, 2), recorded_time))
            conn.commit()
            print(f"💾 ส่งผลลัพธ์ไปที่ Dashboard เรียบร้อย! (Final Score = {final_score:.2f})")
            
    except mysql.connector.Error as err:
        print(f"❌ Error ฐานข้อมูล: {err}")
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()
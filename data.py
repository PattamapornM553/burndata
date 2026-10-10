import mysql.connector
import random
import time
from datetime import datetime

# กำหนดกลุ่ม RFID เป้าหมาย 3 รหัส
rfid_list = ["STD-1010", "STD-2020", "STD-3030"]

try:
    # เชื่อมต่อฐานข้อมูล
    conn = mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="my_database"
    )
    cursor = conn.cursor(dictionary=True)
    
    print("🚀 เริ่มต้นจำลองการส่งข้อมูล 15 ครั้ง...\n")

    # วนลูปจำลองส่งข้อมูล 15 ครั้ง
    for i in range(1, 16):
        # 1. สุ่มตัวแปรต่างๆ
        rfid_uid = random.choice(rfid_list)
        camera_state = random.choice([1, 2])             # สุ่มสถานะ 1 (burntout) หรือ 2 (neutral)
        heart_rate = round(random.uniform(70.0, 110.0), 2) # สุ่มอัตราการเต้นหัวใจ 70 - 110
        survey_answer = round(random.uniform(50.0, 100.0), 2) # สุ่มคะแนนแบบสอบถาม 50 - 100
        recorded_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        # 2. บันทึกข้อมูลดิบรอบนี้
        sql_insert = """INSERT INTO user_logs 
                        (recorded_time, rfid_uid, camera_state, heart_rate, survey_answer) 
                        VALUES (%s, %s, %s, %s, %s)"""
        cursor.execute(sql_insert, (recorded_time, rfid_uid, camera_state, heart_rate, survey_answer))
        conn.commit()

        # 3. นับยอดว่า RFID รหัสนี้สแกนมาแล้วกี่รอบ
        sql_count = "SELECT COUNT(*) AS total_scan FROM user_logs WHERE rfid_uid = %s"
        cursor.execute(sql_count, (rfid_uid,))
        total_scan = cursor.fetchone()['total_scan']
        
        print(f"[{i}] ส่งข้อมูล {rfid_uid} (สแกนรอบที่ {total_scan})")

        # 4. ตรวจสอบว่าครบรอบ 5 ครั้งหรือไม่ (ใช้ % 5 เพื่อให้คำนวณซ้ำได้ทุกๆ 5 รอบ)
        if total_scan > 0 and total_scan % 5 == 0:
            print(f"   🎉 {rfid_uid} สแกนครบ {total_scan} ครั้งแล้ว! กำลังประมวลผล...")
            
            # ดึงข้อมูล 5 แถวล่าสุดมาคำนวณ
            sql_fetch = "SELECT * FROM user_logs WHERE rfid_uid = %s ORDER BY id DESC LIMIT 5"
            cursor.execute(sql_fetch, (rfid_uid,))
            logs = cursor.fetchall()

            sum_heart_rate = 0
            sum_survey = 0
            sum_camera_score = 0
            
            # นำข้อมูลทั้ง 5 รอบมารวมกัน และแปลงคะแนนกล้อง
            for row in logs:
                sum_heart_rate += row['heart_rate']
                sum_survey += row['survey_answer']
                
                # แปลงสถานะกล้องเป็นคะแนน
                if row['camera_state'] == 1:
                    sum_camera_score += 100
                elif row['camera_state'] == 2:
                    sum_camera_score += 50
            
            # หาค่าเฉลี่ยของ 5 รอบ
            avg_heart_rate = sum_heart_rate / 5
            avg_survey = sum_survey / 5
            avg_camera = sum_camera_score / 5
            
            # สมการ Final Score (ปรับแต่งน้ำหนักเปอร์เซ็นต์ได้ตามต้องการ)
            final_score = (avg_heart_rate * 0.4) + (avg_survey * 0.4) + (avg_camera * 0.2)
            
            # 5. บันทึกผลลัพธ์ลงตารางสรุปผล
            sql_summary = """INSERT INTO summary_results (rfid_uid, final_score, created_at) 
                             VALUES (%s, %s, %s)"""
            cursor.execute(sql_summary, (rfid_uid, final_score, recorded_time))
            conn.commit()
            print(f"   💾 บันทึกผลลัพธ์ของ {rfid_uid} (Final Score = {final_score:.2f}) ลงแดชบอร์ดสำเร็จ!\n")

        # หน่วงเวลา 1 วินาทีก่อนส่งข้อมูลรอบถัดไป
        time.sleep(1)

except mysql.connector.Error as err:
    print(f"❌ เกิดข้อผิดพลาดกับฐานข้อมูล: {err}")

finally:
    if 'conn' in locals() and conn.is_connected():
        cursor.close()
        conn.close()
        print("✅ จบการทำงาน ปิดการเชื่อมต่อฐานข้อมูล")
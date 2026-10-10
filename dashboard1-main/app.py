from flask import Flask, render_template, jsonify, request
from datetime import datetime
import threading

app = Flask(__name__)

# ข้อมูลตัวอย่าง
employees = [
    {
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
    },
    {"id": "E002", "name": "Employee 02", "department": "Engineering", "arrival_time": "08:51", "risk": 25, "heart_rate": 76, "facial": "Neutral 92%", "questions": []},
    {"id": "E003", "name": "Employee 03", "department": "Human Resources", "arrival_time": "09:03", "risk": 12, "heart_rate": 72, "facial": "Neutral 95%", "questions": []},
]
lock = threading.Lock()

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/employees')
def get_employees():
    with lock:
        return jsonify({
            "updated_at": datetime.now().isoformat(timespec='seconds'), 
            "employees": employees
        })

@app.route('/api/employees/<employee_id>')
def get_employee(employee_id):
    with lock:
        employee = next((e for e in employees if e['id'] == employee_id), None)
    
    if not employee:
        return jsonify({"error": "employee not found"}), 404
    return jsonify(employee)

@app.post('/api/update')
def update_employee():
    data = request.get_json(force=True)
    employee_id = data.get('id')
    
    with lock:
        employee = next((e for e in employees if e['id'] == employee_id), None)
        
        if employee is None:
            employee = {
                "id": employee_id,
                "name": data.get('name', 'Unknown'),
                "department": data.get('department', ''),
                "arrival_time": data.get('arrival_time', ''),
                "risk": 0,
                "heart_rate": 0,
                "facial": "Neutral",
                "questions": []
            }
            employees.append(employee)
            
        for key in ['name', 'department', 'arrival_time', 'risk', 'heart_rate', 'facial', 'questions']:
            if key in data:
                employee[key] = data[key]
                
    return jsonify({"ok": True, "employee": employee})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
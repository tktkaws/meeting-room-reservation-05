#!/usr/bin/env python3
import sqlite3
import hashlib
from datetime import datetime

# Create database connection
conn = sqlite3.connect('meeting_room.db')
cursor = conn.cursor()

# Read and execute schema
with open('schema.sql', 'r') as f:
    schema = f.read()

# Execute schema statements
for statement in schema.split(';'):
    if statement.strip():
        cursor.execute(statement)

# Insert sample admin user
admin_password = hashlib.sha256('admin123'.encode()).hexdigest()
cursor.execute("""
    INSERT INTO users (name, email, password, role, department) 
    VALUES (?, ?, ?, ?, ?)
""", ('Admin User', 'admin@example.com', admin_password, 'admin', 'IT'))

# Insert sample regular user
user_password = hashlib.sha256('user123'.encode()).hexdigest()
cursor.execute("""
    INSERT INTO users (name, email, password, role, department) 
    VALUES (?, ?, ?, ?, ?)
""", ('Test User', 'user@example.com', user_password, 'user', 'General'))

conn.commit()
conn.close()

print("Database initialized successfully!")
print("Admin: admin@example.com / admin123")
print("User: user@example.com / user123")
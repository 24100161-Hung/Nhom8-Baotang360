"""Chạy dịch vụ phân tích BaoTang360 ngoài Docker: python run.py (cổng PORT, mặc định 8001)."""
import os
import uvicorn

if __name__ == "__main__":
    uvicorn.run("app.main:app", host="0.0.0.0", port=int(os.getenv("PORT", "8001")), reload=False)

# Gói nộp CSE703073 – Nhóm 08 – BaoTang360 (ĐT-16)

Bảo tàng ảo và tour tham quan 360° trên nền web cho Naturalis Biodiversity Center. Đọc **`00_INFO.md`** trước: tệp này có 10 mục theo Mẫu PL-J, bảng ngưỡng bắt buộc tự đánh dấu, cách chạy và tài khoản kiểm thử.

| Mục | Nội dung |
| --- | --- |
| `00_INFO.md` | Thông tin nhóm, URL, Git, video, tài khoản, ngăn xếp, cách chạy, chức năng hoàn thành/chưa hoàn thành, khai báo AI |
| `01_BaoCao/` | Báo cáo dự án (**chưa có**, xem `01_BaoCao/README.md`) |
| `02_SourceCode/` | Mã nguồn (kho Git ở gốc thư mục này chỉ theo dõi phần này): `app/` Laravel 12 + Vue 3 (Inertia), `python-service/` FastAPI, `deploy/`, `compose.yaml`, script `chay`/`dung`/`nap-lai-du-lieu` |
| `03_Database/` | `schema.sql`, `seed.sql`, `erd.mmd`/`erd.png`, `TuDienDuLieu.md`, `kiem_dinh_du_lieu.py`, `README.md` |
| `04_HuongDanSuDung/` | `HuongDan_NguoiDung.md` (du khách), `HuongDan_CaiDat_QuanTri.md` (cài đặt, go-live, sao lưu – khôi phục, quản trị) |
| `05_KiemThu/` | Kết quả kiểm thử (do V5 cập nhật) |
| `06_Demo/` | `screens/` (47 ảnh chụp màn hình), `KichBan_Video.md`, `VideoLink.md`, `README.md` |
| `07_QuanTriDuAn/` | 10 biên bản họp (PL-F), báo cáo mốc M1–M3, nhật ký công việc, phân công (PL-E), khai báo AI (PL-D), tệp Excel `CSE703073_QuanTriDuAn_KiemThu.xlsx` |

Chạy nhanh: nháy đúp `02_SourceCode/chay.bat` (Windows) hoặc `cd 02_SourceCode && ./chay.sh`, rồi mở http://localhost:8080. Đóng gói nộp: `scripts/package.sh 08 MSSV1-MSSV2-MSSV3-MSSV4`; zip riêng hồ sơ (không mã nguồn): `scripts/dong-goi-ho-so.sh`.

Sản phẩm học thuật, phục vụ mục đích đào tạo. Dữ liệu Naturalis dùng theo CC BY-SA 3.0, dữ liệu NBA theo CC0. Thanh toán ở chế độ thử nghiệm.

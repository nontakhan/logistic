// js/script.js
$(document).ready(function() {
    // จัดการการ submit ฟอร์ม addOrderForm
    if ($('#addOrderForm').length) { 
        $('#addOrderForm').on('submit', function(event) {
            event.preventDefault(); 

            // Client-side validation
            let isValid = true;
            let errorMessagesHtml = "";

            if ($('#cssale_docno').val() === "") {
                errorMessagesHtml += "<li>กรุณาเลือกเลขที่บิล</li>";
                isValid = false;
            }
            if ($('#customer_address_origin_id').val() === "") {
                errorMessagesHtml += "<li>กรุณาเลือกที่อยู่ลูกค้า</li>";
                isValid = false;
            }
            if ($('#transport_origin_id').val() === "") {
                errorMessagesHtml += "<li>กรุณาเลือกต้นทางขนส่ง</li>";
                isValid = false;
            }
            // แก้ไข: ลบการตรวจสอบช่องหมายเหตุ (product_details) ออกจากส่วนนี้
            // if ($('#product_details').val().trim() === "") {
            //     errorMessagesHtml += "<li>กรุณากรอกหมายเหตุ</li>";
            //     isValid = false;
            // }
             if ($('#priority').val() === "") {
                errorMessagesHtml += "<li>กรุณาเลือกความเร่งด่วน</li>";
                isValid = false;
            }

            if (!isValid) {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    html: `<ul style='text-align: left; margin-left: 20px;'>${errorMessagesHtml}</ul>`,
                    confirmButtonText: 'ตกลง'
                });
                return;
            }

            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                text: 'กรุณารอสักครู่',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // ส่งข้อมูลด้วย AJAX
            $.ajax({
                url: '../php/submit_order.php', 
                type: 'POST',
                data: $(this).serialize(), 
                dataType: 'json', 
                success: function(response) {
                    Swal.close(); 
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ!',
                            text: response.message,
                            timer: 2000, 
                            showConfirmButton: false
                        }).then(() => {
                            $('#addOrderForm')[0].reset(); 
                            $('.select2-basic').val(null).trigger('change'); // ล้างค่า Select2
                            $('#bill-details-container').slideUp(); // ซ่อนกล่องข้อมูลบิล
                        });
                    } else {
                        let serverErrorMessagesHtml = response.message; 
                        if (response.errors && Object.keys(response.errors).length > 0) {
                            serverErrorMessagesHtml += "<br><br><strong style='font-size: 0.9em;'>รายละเอียดข้อผิดพลาด:</strong><ul style='text-align: left; margin-left: 20px; font-size: 0.85em;'>";
                            for (const key in response.errors) {
                                serverErrorMessagesHtml += `<li>${response.errors[key]}</li>`;
                            }
                            serverErrorMessagesHtml += "</ul>";
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด!',
                            html: serverErrorMessagesHtml, 
                            confirmButtonText: 'ตกลง'
                        });
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    Swal.close(); 
                    let errorDetail = 'ไม่สามารถส่งข้อมูลได้';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorDetail = jqXHR.responseJSON.message;
                    } else if (textStatus && errorThrown) {
                        errorDetail += `: ${textStatus} (${errorThrown})`;
                    } else if (textStatus) {
                         errorDetail += `: ${textStatus}`;
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ',
                        text: errorDetail,
                        confirmButtonText: 'ตกลง'
                    });
                }
            });
        });
    } 
});

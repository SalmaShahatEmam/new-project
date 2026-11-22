<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>رمز التحقق | Retal AlTatweer</title>
</head>

<body style="
    margin:0;
    padding:0;
    background-color:#f4f7f9;
    font-family:Tahoma, Arial, Helvetica, sans-serif;
    color:#24323d;
">

<!-- Preheader -->
<div style="
    display:none;
    max-height:0;
    overflow:hidden;
    opacity:0;
    color:transparent;
">
    رمز التحقق الخاص بك من Retal AlTatweer
</div>

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="background-color:#f4f7f9;"
>
    <tr>
        <td align="center" style="padding:40px 15px;">

            <!-- Main Container -->
            <table
                role="presentation"
                width="100%"
                cellspacing="0"
                cellpadding="0"
                border="0"
                style="
                    max-width:600px;
                    background-color:#ffffff;
                    border-radius:14px;
                    overflow:hidden;
                    border:1px solid #e8edf1;
                "
            >

                <!-- Header -->
                <tr>
                    <td
                        align="center"
                        style="
                            padding:10px 30px;
                            background-color:#00466a;
                        "
                    >
                        <img
                            src="{{ asset('transparent_logo.png') }}"
                            alt="Retal AlTatweer"
                            width="180"
                            style="display:block;width:180px;height:auto;margin:0 auto;"
                        >

                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td
                        style="
                            padding:40px 35px;
                            text-align:right;
                        "
                    >

                        <!-- Title -->
                        <h1 style="
                            margin:0 0 12px;
                            font-size:25px;
                            line-height:36px;
                            font-weight:700;
                            color:#172b38;
                        ">
                            رمز التحقق
                        </h1>

                        <p style="
                            margin:0 0 25px;
                            font-size:15px;
                            line-height:28px;
                            color:#667781;
                        ">
                            Verification Code
                        </p>


                        <!-- Arabic Message -->
                        <p style="
                            margin:0 0 10px;
                            font-size:16px;
                            line-height:30px;
                            color:#263842;
                        ">
                            مرحبًا،
                        </p>

                        <p style="
                            margin:0 0 25px;
                            font-size:15px;
                            line-height:28px;
                            color:#52636d;
                        ">
                            استخدم رمز التحقق التالي لإكمال عملية تسجيل الدخول أو التحقق من حسابك.
                        </p>


                        <!-- OTP Label -->
                        <p style="
                            margin:0 0 10px;
                            text-align:center;
                            font-size:13px;
                            line-height:20px;
                            color:#7a8992;
                        ">
                            رمز التحقق الخاص بك
                        </p>


                        <!-- OTP -->
                        <table
                            role="presentation"
                            width="100%"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                        >
                            <tr>
                                <td align="center">

                                    <div style="
                                        display:inline-block;
                                        padding:16px 28px;
                                        background-color:#eef6fa;
                                        border:1px solid #cfe3ed;
                                        border-radius:10px;
                                        font-family:Arial, Helvetica, sans-serif;
                                        font-size:32px;
                                        line-height:40px;
                                        font-weight:700;
                                        letter-spacing:8px;
                                        color:#00466a;
                                        direction:ltr;
                                    ">
                                        {{ $code }}
                                    </div>

                                </td>
                            </tr>
                        </table>


                        <!-- Expiration -->
                        <div style="
                            margin-top:22px;
                            padding:14px 18px;
                            background-color:#fff8e8;
                            border:1px solid #f3dfad;
                            border-radius:8px;
                            text-align:center;
                        ">

                            <p style="
                                margin:0;
                                font-size:14px;
                                line-height:24px;
                                color:#806b35;
                            ">
                                ⏱ هذا الرمز صالح لمدة
                                <strong>3 دقائق فقط</strong>.
                            </p>

                        </div>


                        <!-- Security -->
                        <p style="
                            margin:25px 0 0;
                            font-size:14px;
                            line-height:25px;
                            color:#687982;
                        ">
                            🔒 حفاظًا على أمان حسابك، لا تشارك رمز التحقق مع أي شخص.
                        </p>

                        <p style="
                            margin:8px 0 0;
                            font-size:13px;
                            line-height:23px;
                            color:#89969d;
                        ">
                            إذا لم تطلب هذا الرمز، يمكنك تجاهل هذه الرسالة بأمان.
                        </p>


                        <!-- Divider -->
                        <div style="
                            height:1px;
                            background-color:#edf0f2;
                            margin:32px 0 25px;
                        "></div>


                        <!-- English Version -->
                        <div
                            dir="ltr"
                            style="
                                text-align:left;
                            "
                        >

                            <p style="
                                margin:0 0 10px;
                                font-size:14px;
                                line-height:24px;
                                color:#52636d;
                            ">
                                Hello,
                            </p>

                            <p style="
                                margin:0;
                                font-size:14px;
                                line-height:24px;
                                color:#687982;
                            ">
                                Use the verification code above to complete your
                                sign-in or account verification.
                            </p>

                            <p style="
                                margin:10px 0 0;
                                font-size:13px;
                                line-height:22px;
                                color:#89969d;
                            ">
                                This code expires in 3 minutes.
                                Please do not share it with anyone.
                            </p>

                        </div>

                    </td>
                </tr>


                <!-- Footer -->
                <tr>
                    <td
                        align="center"
                        style="
                            padding:25px 30px;
                            background-color:#f8fafb;
                            border-top:1px solid #edf0f2;
                        "
                    >

                        <p style="
                            margin:0 0 8px;
                            font-size:14px;
                            line-height:22px;
                            font-weight:700;
                            color:#00466a;
                        ">
                            Retal Al Tatweer
                        </p>

                        <p style="
                            margin:0;
                            font-size:12px;
                            line-height:20px;
                            color:#8a979e;
                        ">
                            © {{ date('Y') }} Retal AlTatweer.
                            All rights reserved.
                        </p>

                        <p style="
                            margin:7px 0 0;
                            font-size:11px;
                            line-height:18px;
                            color:#a1abb0;
                        ">
                            This is an automated message. Please do not reply.
                        </p>

                    </td>
                </tr>

            </table>

            <!-- Bottom Space -->
            <p style="
                margin:18px 0 0;
                font-size:11px;
                line-height:18px;
                color:#a1abb0;
                text-align:center;
            ">
                Retal AlTatweer
            </p>

        </td>
    </tr>
</table>

</body>
</html>
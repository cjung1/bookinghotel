const express = require('express');
const bodyParser = require('body-parser');
const nodemailer = require('nodemailer');
const cors = require('cors');

const app = express();
const port = 3001; 

app.use(cors());
app.use(bodyParser.json());

app.post('/send-otp-email', async (req, res) => {
    console.log('Received request to /send-otp-email:', req.body);
    const { toEmail, otp } = req.body;

    const transporter = nodemailer.createTransport({
        service: 'Gmail', 
        auth: {
            user: 'hai2k4z@gmail.com', 
            pass: 'yggcjslsczdiejnm' 
        }
    });

    const mailOptions = {
        from: 'hai2k4z@gmail.com', 
        to: toEmail,
        subject: 'Mã xác nhận CONANDOYCLEHOTELBOOKING',
        html: `<p>Xin chào!</p><p>Mã xác nhận của bạn là: <strong>${otp}</strong></p><p>Mã có hiệu lực trong 5 phút.</p>`
    };

    try {
        console.log('Attempting to send email to:', toEmail, 'with OTP:', otp);
        const info = await transporter.sendMail(mailOptions);
        console.log('Email sent: ' + info.response);
        res.json({ success: true, message: 'Email đã được gửi!' });
    } catch (error) {
        console.error('Lỗi gửi email:', error);
        res.status(500).json({ success: false, message: 'Lỗi khi gửi email.' });
    }
});

app.listen(port, () => {
    console.log(`Mailer server is running on port ${port}`);
});
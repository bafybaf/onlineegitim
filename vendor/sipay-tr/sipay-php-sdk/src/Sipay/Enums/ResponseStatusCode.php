<?php

declare(strict_types=1);

namespace Sipay\Enums;

final class ResponseStatusCode
{
    public const BASIC_VALIDATION = 1;
    public const INVOICE_ID_ALREADY_PROCESSED = 3;
    public const INVALID_ITEMS = 12;
    public const TOTAL_MISMATCH = 13;
    public const MERCHANT_NOT_FOUND = 14;
    public const INVALID_CREDENTIALS = 30;
    public const TRANSACTION_NOT_FOUND = 31;
    public const INVALID_INVOICE_ID = 32;
    public const AMOUNT_MUST_BE_INTEGER = 33;
    public const PAYMENT_INTEGRATION_NOT_ALLOWED = 34;
    public const CREDIT_CARD_PAYMENT_OPTION_NOT_DEFINED = 35;
    public const POS_NOT_FOUND = 36;
    public const MERCHANT_POS_COMMISSION_NOT_SET = 37;
    public const MERCHANT_COMMISSION_NOT_SET = 38;
    public const COMMISSION_NOT_FOUND = 39;
    public const INSTALLMENT_NOT_FOUND = 40;
    public const ORDER_FAILED = 41;
    public const PRODUCT_PRICE_TOO_LOW = 42;
    public const PAYMENT_DUE_NOT_SET = 43;
    public const CREDIT_CARD_BLOCKED = 44;
    public const MERCHANT_DAILY_TRANSACTION_LIMIT_EXCEEDED = 45;
    public const MERCHANT_MONTHLY_TRANSACTION_COUNT_EXCEEDED = 46;
    public const MERCHANT_MONTHLY_TRANSACTION_AMOUNT_EXCEEDED = 47;
    public const MINIMUM_TRANSACTION_LIMIT_VIOLATED = 48;
    public const REFUND_FAILED = 49;
    public const RECURRING_PAYMENT_ERROR = 55;
    public const INVALID_SALE_WEBHOOK_KEY = 56;
    public const MERCHANT_CARD_USAGE_NOT_ALLOWED = 60;
    public const HASH_KEY_MISMATCH = 68;
    public const ORDER_NOT_PROCESSED = 69;
    public const CARD_PROGRAM_MISMATCH = 70;
    public const RECURRING_PLAN_UPDATE_FAILED = 71;
    public const DELETE_FAILED = 72;
    public const RECURRING_PLAN_CARD_ADD_FAILED = 73;
    public const FOREIGN_CARDS_NOT_ALLOWED = 76;
    public const FOREIGN_CARD_COMMISSION_NOT_SET = 77;
    public const CARD_TOKEN_PAYMENT_NOT_ALLOWED = 79;
    public const SUB_MERCHANT_NOT_FOUND = 80;
    public const CURRENCY_CONVERSION_FAILED = 81;
    public const INVALID_CHARACTER = 85;
    public const CARD_TOKEN_SAVE_FAILED = 86;
    public const INVALID_TOKEN_OR_CUSTOMER_NUMBER = 87;
    public const CARD_TOKEN_DELETE_FAILED = 88;
    public const CARD_TOKEN_UPDATE_FAILED = 89;
    public const INVALID_HASH_KEY = 90;
    public const HASH_KEY_MERCHANT_KEY_MISMATCH = 91;
    public const HASH_KEY_CUSTOMER_NUMBER_MISMATCH = 92;
    public const HASH_KEY_CARDHOLDER_NAME_MISMATCH = 93;
    public const HASH_KEY_CARD_NUMBER_MISMATCH = 94;
    public const HASH_KEY_EXPIRY_MONTH_MISMATCH = 95;
    public const HASH_KEY_EXPIRY_YEAR_MISMATCH = 96;
    public const HASH_KEY_CARD_TOKEN_MISMATCH = 97;
    public const UNKNOWN_ERROR = 99;
    public const SUCCESS = 100;
    public const REFUND_REQUEST_CREATED = 101;
    public const POS_NOT_DEFINED = 102;
    public const PAYBY_TOKEN_COMMISSION_NOT_SET = 103;
    public const REFUND_TRANSACTION_ID_NOT_UNIQUE = 104;
    public const TRANSACTION_NOT_APPROVED = 105;
    public const INVALID_MERCHANT_TYPE = 106;
    public const API_OPT_VALIDATION_FAILED = 107;
    public const INVALID_CARD_NUMBER = 108;
    public const FILE_PROCESSING_ERROR = 109;
    public const PARTIAL_REFUND_NOT_ALLOWED = 110;
    public const PARTIALLY_SUCCESSFUL = 112;
    public const INVOICE_ID_ALREADY_PROCESSED_117 = 117;
    public const CANCEL_URL_REQUIRED = 404;

    protected const DESCRIPTIONS = [
        self::BASIC_VALIDATION => 'Temel doğrulama',
        self::INVOICE_ID_ALREADY_PROCESSED => 'Fatura kimliği çoktan işlendi',
        self::INVALID_ITEMS => 'Öğeler bir dizi olmalı, Geçersiz Para Birimi kodu, Geçersiz öğe biçimi',
        self::TOTAL_MISMATCH => 'Ürün fiyatınızın toplamı ("5"), fatura toplamına ("10") eşit değil',
        self::MERCHANT_NOT_FOUND => 'Üye işyeri bulunamadı!',
        self::INVALID_CREDENTIALS => 'Geçersiz kimlik bilgileri',
        self::TRANSACTION_NOT_FOUND => 'İşlem bulunamadı',
        self::INVALID_INVOICE_ID => 'Geçersiz fatura kimliği, sipariş tamamlanmadı',
        self::AMOUNT_MUST_BE_INTEGER => 'Miktar tam sayı olmalıdır',
        self::PAYMENT_INTEGRATION_NOT_ALLOWED => 'Ödeme entegrasyon yöntemine izin verilmiyor. Lütfen desteğe başvurunuz.',
        self::CREDIT_CARD_PAYMENT_OPTION_NOT_DEFINED => 'Kredi Kartı Ödeme Seçeneği tanımlanmadı',
        self::POS_NOT_FOUND => 'Pos bulunamadı',
        self::MERCHANT_POS_COMMISSION_NOT_SET => 'Üye işyeri Pos Komisyonu ayarlanmadı. Lütfen servis sağlayıcı ile iletişime geçiniz.',
        self::MERCHANT_COMMISSION_NOT_SET => 'Bu para birimi ve ödeme yöntemi için Üye İşyeri Komisyonu belirlenmedi. Lütfen başka bir ödeme yöntemi deneyiniz',
        self::COMMISSION_NOT_FOUND => 'Komisyon bulunamadı',
        self::INSTALLMENT_NOT_FOUND => 'Taksit bulunamadı',
        self::ORDER_FAILED => 'Sipariş başarısız',
        self::PRODUCT_PRICE_TOO_LOW => 'Ürün fiyatı komisyondan az, Ürün fiyatı maliyetten düşük',
        self::PAYMENT_DUE_NOT_SET => 'Ödeme vadesi ayarlanmamış',
        self::CREDIT_CARD_BLOCKED => 'Bu kredi kartı bloke edilmiştir.',
        self::MERCHANT_DAILY_TRANSACTION_LIMIT_EXCEEDED => 'Üye İşyeri günlük işlem tutarı sınırı aşıldı',
        self::MERCHANT_MONTHLY_TRANSACTION_COUNT_EXCEEDED => 'Üye İşyeri aylık işlem sayısı sınırı aşıldı',
        self::MERCHANT_MONTHLY_TRANSACTION_AMOUNT_EXCEEDED => 'Üye İşyeri aylık işlem tutarı sınırı aşıldı',
        self::MINIMUM_TRANSACTION_LIMIT_VIOLATED => 'İşlem başına minimum işlem limiti ihlal edildi',
        self::REFUND_FAILED => 'İade Başarısız',
        self::RECURRING_PAYMENT_ERROR => 'Yinelenen ödemede taksitli satış olamaz',
        self::INVALID_SALE_WEBHOOK_KEY => 'Geçersiz satış webhook anahtarı! Lütfen Sipay\'deki anahtar adını kontrol ediniz',
        self::MERCHANT_CARD_USAGE_NOT_ALLOWED => 'Üye iş yerinin bu kartı kullanarak işlem yapmasına izin verilmez.',
        self::HASH_KEY_MISMATCH => 'Hash key ile toplam tutar uyuşmazlığı',
        self::ORDER_NOT_PROCESSED => 'Sipariş henüz işlenmedi',
        self::CARD_PROGRAM_MISMATCH => 'Kart programı uyuşmazlığı',
        self::RECURRING_PLAN_UPDATE_FAILED => 'Yinelenen plan güncellemesi başarısız oldu, Geçersiz Yanıt veya Bilinmeyen Hata',
        self::DELETE_FAILED => 'Silinemedi, Eski ödeme işlenemedi',
        self::RECURRING_PLAN_CARD_ADD_FAILED => 'Yinelenen plan kartı eklenemedi, Geçersiz Yanıt veya Bilinmeyen Hata',
        self::FOREIGN_CARDS_NOT_ALLOWED => 'Bu Üye İşyeri için Yabancı Kartlara İzin Verilmemektedir.',
        self::FOREIGN_CARD_COMMISSION_NOT_SET => 'Bu Üye İşyeri için Yabancı Kart Komisyonu belirlenmemiş',
        self::CARD_TOKEN_PAYMENT_NOT_ALLOWED => 'Üye işyerinin kart tokenı ile ödeme yapmasına izin verilmez',
        self::SUB_MERCHANT_NOT_FOUND => 'Alt Üye işyeri bulunamadı',
        self::CURRENCY_CONVERSION_FAILED => 'API\'den para birimi dönüştürme başarısız oldu',
        self::INVALID_CHARACTER => 'Geçersiz karakter',
        self::CARD_TOKEN_SAVE_FAILED => 'Kart token kaydetme başarısız oldu',
        self::INVALID_TOKEN_OR_CUSTOMER_NUMBER => 'Geçersiz token veya müşteri numarası',
        self::CARD_TOKEN_DELETE_FAILED => 'Kart tokenı silinemedi',
        self::CARD_TOKEN_UPDATE_FAILED => 'Kart tokenı güncellenemedi',
        self::INVALID_HASH_KEY => 'Geçersiz Hash key',
        self::HASH_KEY_MERCHANT_KEY_MISMATCH => 'Üye İşyeri anahtarı ile uyuşmayan Hash key',
        self::HASH_KEY_CUSTOMER_NUMBER_MISMATCH => 'Müşteri numarası ile uyuşmayan Hash key',
        self::HASH_KEY_CARDHOLDER_NAME_MISMATCH => 'Kart Sahibinin Adı ile uyuşmayan Hash key',
        self::HASH_KEY_CARD_NUMBER_MISMATCH => 'Kart Numarası ile uyuşmayan Hash key',
        self::HASH_KEY_EXPIRY_MONTH_MISMATCH => 'Son kullanma ayı ile uyuşmayan Hash key',
        self::HASH_KEY_EXPIRY_YEAR_MISMATCH => 'Son kullanma yılı ile uyuşmayan Hash key',
        self::HASH_KEY_CARD_TOKEN_MISMATCH => 'Kart tokenı ile uyuşmayan Hash key',
        self::UNKNOWN_ERROR => 'Bilinmeyen hata',
        self::SUCCESS => 'Başarılı',
        self::REFUND_REQUEST_CREATED => 'İade talebiniz başarıyla oluşturuldu. Ekibimiz iade işlemini tamamlayacaktır',
        self::POS_NOT_DEFINED => 'Pos tanımlanmadı, bu taksit için Pos bulunamadı',
        self::PAYBY_TOKEN_COMMISSION_NOT_SET => 'Payby token komisyon oranı oluşturulmadı',
        self::REFUND_TRANSACTION_ID_NOT_UNIQUE => 'İade işlem ID si benzersiz olmalı',
        self::TRANSACTION_NOT_APPROVED => 'İşlem onaylanmadı',
        self::INVALID_MERCHANT_TYPE => 'Geçersiz üye işyeri tipi',
        self::API_OPT_VALIDATION_FAILED => 'Gönderilen api opt doğrulanamadı',
        self::INVALID_CARD_NUMBER => 'Geçersiz kart numarası',
        self::FILE_PROCESSING_ERROR => 'Dosya işleme hatası',
        self::PARTIAL_REFUND_NOT_ALLOWED => 'Parçalı iade bu işlem için izin verilmemektedir',
        self::PARTIALLY_SUCCESSFUL => 'Kısmen başarılı',
        self::INVOICE_ID_ALREADY_PROCESSED_117 => 'Fatura kimliği zaten işlendi',
        self::CANCEL_URL_REQUIRED => 'İptal URL\'si boş bırakılmamalıdır',
    ];

    public static function getDescription(int $statusCode): ?string
    {
        return self::DESCRIPTIONS[$statusCode] ?? null;
    }
}

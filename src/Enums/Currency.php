<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * Currencies supported by Telegram Bot API
 *
 * Source: https://core.telegram.org/bots/payments#supported-currencies
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum Currency: string
{
  case Aed = 'AED';
  case Afn = 'AFN';
  case All = 'ALL';
  case Amd = 'AMD';
  case Ars = 'ARS';
  case Aud = 'AUD';
  case Azn = 'AZN';
  case Bam = 'BAM';
  case Bdt = 'BDT';
  case Bgn = 'BGN';
  case Bnd = 'BND';
  case Bob = 'BOB';
  case Brl = 'BRL';
  case Byn = 'BYN';
  case Cad = 'CAD';
  case Chf = 'CHF';
  case Clp = 'CLP';
  case Cny = 'CNY';
  case Cop = 'COP';
  case Crc = 'CRC';
  case Czk = 'CZK';
  case Dkk = 'DKK';
  case Dop = 'DOP';
  case Dzd = 'DZD';
  case Egp = 'EGP';
  case Etb = 'ETB';
  case Eur = 'EUR';
  case Gbp = 'GBP';
  case Gel = 'GEL';
  case Gtq = 'GTQ';
  case Hkd = 'HKD';
  case Hnl = 'HNL';
  case Hrk = 'HRK';
  case Huf = 'HUF';
  case Idr = 'IDR';
  case Ils = 'ILS';
  case Inr = 'INR';
  case Isk = 'ISK';
  case Jmd = 'JMD';
  case Jpy = 'JPY';
  case Kes = 'KES';
  case Kgs = 'KGS';
  case Krw = 'KRW';
  case Kzt = 'KZT';
  case Lbp = 'LBP';
  case Lkr = 'LKR';
  case Mad = 'MAD';
  case Mdl = 'MDL';
  case Mnt = 'MNT';
  case Mur = 'MUR';
  case Mvr = 'MVR';
  case Mxn = 'MXN';
  case Myr = 'MYR';
  case Mzn = 'MZN';
  case Ngn = 'NGN';
  case Nio = 'NIO';
  case Nok = 'NOK';
  case Npr = 'NPR';
  case Nzd = 'NZD';
  case Pab = 'PAB';
  case Pen = 'PEN';
  case Php = 'PHP';
  case Pkr = 'PKR';
  case Pln = 'PLN';
  case Pyg = 'PYG';
  case Qar = 'QAR';
  case Ron = 'RON';
  case Rsd = 'RSD';
  case Rub = 'RUB';
  case Sar = 'SAR';
  case Sek = 'SEK';
  case Sgd = 'SGD';
  case Thb = 'THB';
  case Tjs = 'TJS';
  case Try = 'TRY';
  case Ttd = 'TTD';
  case Twd = 'TWD';
  case Tzs = 'TZS';
  case Uah = 'UAH';
  case Ugx = 'UGX';
  case Usd = 'USD';
  case Uyu = 'UYU';
  case Uzs = 'UZS';
  case Vnd = 'VND';
  case Yer = 'YER';
  case Zar = 'ZAR';
}

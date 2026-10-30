<!DOCTYPE html>
<html>

<head>
    <title>{{ groupName() }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <style>
        .RowHeader {
            display: table;
            width: 100%; /*Optional*/          
        }
        .Column1 {
            display: table-cell;
            width: 20%;
        }
        .Column2 {
            display: table-cell;
            width: 60%;
        }
        .Column3 {
            display: table-cell;
            width: 20%;
        }

        h1, h2, h3, h4, h5, h6 {
            margin-top:15;
            margin-bottom:15;
        }
    </style>
</head>

<body>
    <div class="RowHeader">
        <div class="Column1">
                @if ($logoPath = groupLogoPath())
                    <img src="{{ $logoPath }}" height="40" width="80" alt="{{ groupName() }}">
                @endif
        </div>
        <div class="Column2">
            <h2 align="center" style="color:SteelBlue;" > {{ groupName() }}  </h2>
            @if (filled(getSettings('registration_number')) && getSettings('registration_number') !== 'NA')
                <p align="center" style="margin:0;">Reg. No. {{ getSettings('registration_number') }}</p>
            @endif
        </div>
        <div class="Column3">
        </div>
    
    </div>
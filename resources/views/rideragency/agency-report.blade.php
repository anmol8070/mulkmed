@extends('include.app')

@php
    $pageTitle = 'Agency Report';
    $pageSubtitle = '';
@endphp

@section('header')
<link rel="stylesheet" href="{{ asset('asset/css/rideragency/agency-report.css') }}">
@endsection

@section('content')
<div class="content">

    <!-- BLUE INFO SECTION -->
    <div class="info-wrapper">

        <!-- BACK BUTTON -->
        <a href="{{ route('rideragency.agencies') }}" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>

        <!-- AGENCY INFO -->
        <div class="agency-info">

            <div class="agency-left">
                <img src="{{ asset('asset/image/hotel.png') }}" alt="Agency">

                <div>
                    <div class="agency-name">Mercure Hotel</div>
                    <div class="agency-meta agency-id">Agency ID: 1</div>
                    <div class="agency-meta agency-address">
                        Suite 402, Al-Moosa Tower 2, Sheikh Zayed Road, Dubai, UAE
                    </div>
                </div>
            </div>

            <div class="agency-right">
                Available No. Of Riders
                <strong>100</strong>
            </div>

        </div>
    </div>

    <!-- TRANSACTION SUMMARY -->
    <div class="card">
        <h4>Transaction Summary</h4>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Amount Paid</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>25<span class="date-sup">th</span> Dec 2025</td>
                    <td>10:00 AM</td>
                    <td>AED 100</td>
                    <td><a href="#" class="download"><i class="fas fa-download"></i> Download Invoice</a></td>
                </tr>
                <tr>
                    <td>24<span class="date-sup">th</span> Dec 2025</td>
                    <td>10:00 AM</td>
                    <td>AED 100</td>
                    <td><a href="#" class="download"><i class="fas fa-download"></i> Download Invoice</a></td>
                </tr>
                <tr>
                    <td>20<span class="date-sup">th</span> Dec 2025</td>
                    <td>10:00 AM</td>
                    <td>AED 100</td>
                    <td><a href="#" class="download"><i class="fas fa-download"></i> Download Invoice</a></td>
                </tr>
                <tr>
                    <td>19<span class="date-sup">th</span> Dec 2025</td>
                    <td>10:00 AM</td>
                    <td>AED 100</td>
                    <td><a href="#" class="download"><i class="fas fa-download"></i> Download Invoice</a></td>
                </tr>
                <tr>
                    <td>16<span class="date-sup">th</span> Dec 2025</td>
                    <td>10:00 AM</td>
                    <td>AED 100</td>
                    <td><a href="#" class="download"><i class="fas fa-download"></i> Download Invoice</a></td>
                </tr>
                <tr>
                    <td>15<span class="date-sup">th</span> Dec 2025</td>
                    <td>10:00 AM</td>
                    <td>AED 100</td>
                    <td><a href="#" class="download"><i class="fas fa-download"></i> Download Invoice</a></td>
                </tr>
                <tr>
                    <td>14<span class="date-sup">th</span> Dec 2025</td>
                    <td>10:00 AM</td>
                    <td>AED 100</td>
                    <td><a href="#" class="download"><i class="fas fa-download"></i> Download Invoice</a></td>
                </tr>
                <tr>
                    <td>10<span class="date-sup">th</span> Dec 2025</td>
                    <td>10:00 AM</td>
                    <td>AED 100</td>
                    <td><a href="#" class="download"><i class="fas fa-download"></i> Download Invoice</a></td>
                </tr>
                <tr>
                    <td>8<span class="date-sup">th</span> Dec 2025</td>
                    <td>10:00 AM</td>
                    <td>AED 100</td>
                    <td><a href="#" class="download"><i class="fas fa-download"></i> Download Invoice</a></td>
                </tr>
            </tbody>
        </table>
    </div>

</div>
@endsection

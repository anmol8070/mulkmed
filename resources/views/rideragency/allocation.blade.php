@extends('include.app')

@php
$pageTitle = 'Rider Allocation';
$pageSubtitle = '';
@endphp

@section('header')
<link rel="stylesheet" href="{{ asset('asset/css/rideragency/allocation.css') }}">
@endsection

@section('content')
<div class="content">

    <!-- ALLOCATION FORM -->
<div class="card">
    <div class="form-grid">

        <div>
            <label>Agency Name</label>
            <select>
                <option>Select</option>
            </select>
        </div>

        <div>
            <label>Select Type</label>
            <select>
                <option>Select</option>
            </select>
        </div>

        <div>
            <label>Price Per Rider</label>
            <input type="text" placeholder="Enter Price">
        </div>

        <div>
            <label>Number Of Riders</label>
            <input type="text" placeholder="Enter Number">
        </div>

        <!-- BUTTON (NEXT LINE) -->
        <div class="allocate-row">
            <button class="btn-primary">Allocate</button>
        </div>

    </div>
</div>



    <!-- ALLOCATED AGENCIES -->
    <div class="section-header">
        <h4>Allocated Agencies</h4>

        <div class="search-box">
            <input type="text" placeholder="Search Agency">
        </div>
    </div>

    <table>
        <tr>
            <th>Agency Name</th>
            <th>Type</th>
            <th>Price</th>
            <th>Number Of Riders</th>
            <th></th>
        </tr>

        <tr>
            <td>Global Travels Pvt. Ltd.</td>
            <td><span class="badge"><i class="fas fa-plane"></i> Travel</span></td>
            <td class="price">AED 100</td>
            <td class="riders">500</td>
            <td><a href="#" class="edit">Edit</a></td>
        </tr>

        <tr>
            <td>Royal Amber Resorts</td>
            <td><span class="badge"><i class="fas fa-hotel"></i> Hotel</span></td>
            <td class="price">AED 120</td>
            <td class="riders">500</td>
            <td><a href="#" class="edit">Edit</a></td>
        </tr>

        <tr>
            <td>Gulf Stream Documentation</td>
            <td><span class="badge">VISA</span></td>
            <td class="price">AED 150</td>
            <td class="riders">500</td>
            <td><a href="#" class="edit">Edit</a></td>
        </tr>

        <tr>
            <td>Global Travels Pvt. Ltd.</td>
            <td><span class="badge"><i class="fas fa-plane"></i> Travel</span></td>
            <td class="price">AED 100</td>
            <td class="riders">500</td>
            <td><a href="#" class="edit">Edit</a></td>
        </tr>

        <tr>
            <td>Royal Amber Resorts</td>
            <td><span class="badge"><i class="fas fa-hotel"></i> Hotel</span></td>
            <td class="price">AED 120</td>
            <td class="riders">500</td>
            <td><a href="#" class="edit">Edit</a></td>
        </tr>

        <tr>
            <td>Gulf Stream Documentation</td>
            <td><span class="badge">VISA</span></td>
            <td class="price">AED 150</td>
            <td class="riders">500</td>
            <td><a href="#" class="edit">Edit</a></td>
        </tr>
    </table>

</div>
@endsection
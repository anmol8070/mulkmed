@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/patient_appointment/registration.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"
        integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@endsection

<style>
  /* MAIN WRAPPER */
    .reg-wrapper {
        max-width: 600px;
        margin: 0 auto;
        padding: 40px 20px;
        background: #ffffff;
    }

    /* TITLE */
    .reg-title {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 25px;
        color: #000;
    }

    /* ILLUSTRATION */
    .reg-illustration {
        width: 100%;
        display: flex;
        justify-content: center;
        margin-bottom: 30px;
    }
    .reg-illustration img {
        width: 240px;
    }

    /* FORM */
    .reg-form {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    /* GROUP */
    .reg-group {
        width: 100%;
    }

    /* LABEL */
    .reg-label {
        font-weight: 600;
        color: #333;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* ICON */
    .reg-icon {
        font-size: 20px;
        color: #555;
    }

    /* INPUT */
    .reg-input {
        width: 100%;
        padding: 10px 0;
        border: none;
        border-bottom: 1.5px solid #d1d1d1;
        background: none;
        font-size: 15px;
        outline: none;
        transition: .2s;
    }
    .reg-input:focus {
        border-bottom: 1.5px solid #0ea5e9;
    }

    /* SELECT */
    .reg-select {
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        border: 1.3px solid #d1d1d1;
        background: #fff;
        outline: none;
        font-size: 15px;
        transition: .2s;
    }
    .reg-select:focus {
        border-color: #0ea5e9;
    }

    /* ERROR TEXT */
    .reg-error {
        font-size: 13px;
        color: #ef4444;
        margin-top: 4px;
    }

    /* BUTTON */
    .reg-btn {
        width: 100%;
        margin-top: 20px;
        padding: 14px;
        background: #0284C7;
        color: #fff;
        font-size: 18px;
        font-weight: 600;
        border-radius: 40px;
        border: none;
        cursor: pointer;
    }
    .reg-btn:hover {
        background: #0369a1;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
    font-size: 14px !important;
}

.select2-container .select2-selection--single {
    height: 38px !important;
    padding: 4px 8px !important;
    font-size: 14px !important;
}




</style>

@section('content')
    <div class="reg-wrapper">

    <div class="reg-illustration">
        <img src="{{ asset('/storage/uploads/patient_registration.png') }}" alt="">
    </div>

    @if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
    @endif


    <h2 class="reg-title">Add Patient</h2>

    <form class="reg-form" method="POST" action="{{ route('patientAppointment.storeRegistration') }}">
        @csrf

        <!-- Full Name -->
        <div class="reg-group">
            <label class="reg-label">
                <i class="fa fa-user reg-icon"></i> Full Name
            </label>
            <input type="text" name="fullname" value="{{ old('fullname') }}" class="reg-input" required>
            @error('fullname')
                <p class="reg-error">{{ $message }}</p>
            @enderror
        </div>

        <!-- Gender -->
        <div class="reg-group">
            <label class="reg-label">
                <i class="fa fa-venus-mars reg-icon"></i> Gender
            </label>
            <select name="gender" id="gender" class="reg-select" required>
                <option value="" disabled {{ old('gender') !== null && old('gender') !== '' ? '' : 'selected' }}>Select</option>
                <option value="{{ \App\Models\Constants::genderMale }}" {{ (string) old('gender') === (string) \App\Models\Constants::genderMale ? 'selected' : '' }}>Male</option>
                <option value="{{ \App\Models\Constants::genderFemale }}" {{ (string) old('gender') === (string) \App\Models\Constants::genderFemale ? 'selected' : '' }}>Female</option>
            </select>
            @error('gender')
                <p class="reg-error">{{ $message }}</p>
            @enderror
        </div>

        <!-- DOB -->
        <div class="reg-group">
            <label class="reg-label">
                <i class="fa fa-birthday-cake reg-icon"></i> Date Of Birth
            </label>
            <input type="date" name="dob" value="{{ old('dob') }}" class="reg-input" required>
            @error('dob')
                <p class="reg-error">{{ $message }}</p>
            @enderror
        </div>

        <!-- Email -->
        <div class="reg-group">
            <label class="reg-label">
                <i class="fa fa-envelope reg-icon"></i> Email Address
            </label>
            <input type="email" name="email" value="{{ old('email') }}" class="reg-input">
            @error('email')
                <p class="reg-error">{{ $message }}</p>
            @enderror
        </div>

        <!-- Username -->
        <div class="reg-group">
            <label class="reg-label">
                <i class="fa fa-user-circle reg-icon"></i> Username
            </label>
            <input type="text" name="username" value="{{ old('username') }}" class="reg-input" required>
            @error('username')
                <p class="reg-error">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div class="reg-group">
            <label class="reg-label">
                <i class="fa fa-lock reg-icon"></i> Password
            </label>
            <input type="password" name="password" class="reg-input" required>
            @error('password')
                <p class="reg-error">{{ $message }}</p>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="reg-group">
            <label class="reg-label">
                <i class="fa fa-lock reg-icon"></i> Confirm Password
            </label>
            <input type="password" name="confirm_password" class="reg-input" required>
            @error('confirm_password')
                <p class="reg-error">{{ $message }}</p>
            @enderror
        </div>

        <!-- Phone Number -->
        <div class="reg-group">
    <label class="reg-label">
        <i class="fa fa-id-card reg-icon"></i> Phone Number
    </label>

    <div style="display:flex; gap:8px; align-items:center;">
        
        <!-- Country Code Select -->
<select id="nubmerSelect" name="country_code" class="reg-input select2" style="width:80px;">
    <option value="93">+93 Afghanistan</option>
    <option value="355">+355 Albania</option>
    <option value="213">+213 Algeria</option>
    <option value="1">+1 American Samoa</option>
    <option value="376">+376 Andorra</option>
    <option value="244">+244 Angola</option>
    <option value="1">+1 Anguilla</option>
    <option value="672">+672 Antarctica</option>
    <option value="1">+1 Antigua and Barbuda</option>
    <option value="54">+54 Argentina</option>
    <option value="374">+374 Armenia</option>
    <option value="297">+297 Aruba</option>
    <option value="61">+61 Australia</option>
    <option value="43">+43 Austria</option>
    <option value="994">+994 Azerbaijan</option>
    <option value="1">+1 Bahamas</option>
    <option value="973">+973 Bahrain</option>
    <option value="880">+880 Bangladesh</option>
    <option value="1">+1 Barbados</option>
    <option value="375">+375 Belarus</option>
    <option value="32">+32 Belgium</option>
    <option value="501">+501 Belize</option>
    <option value="229">+229 Benin</option>
    <option value="1">+1 Bermuda</option>
    <option value="975">+975 Bhutan</option>
    <option value="591">+591 Bolivia</option>
    <option value="387">+387 Bosnia and Herzegovina</option>
    <option value="267">+267 Botswana</option>
    <option value="55">+55 Brazil</option>
    <option value="246">+246 British Indian Ocean Territory</option>
    <option value="1">+1 British Virgin Islands</option>
    <option value="673">+673 Brunei</option>
    <option value="359">+359 Bulgaria</option>
    <option value="226">+226 Burkina Faso</option>
    <option value="257">+257 Burundi</option>
    <option value="855">+855 Cambodia</option>
    <option value="237">+237 Cameroon</option>
    <option value="1">+1 Canada</option>
    <option value="238">+238 Cape Verde</option>
    <option value="1">+1 Cayman Islands</option>
    <option value="236">+236 Central African Republic</option>
    <option value="235">+235 Chad</option>
    <option value="56">+56 Chile</option>
    <option value="86">+86 China</option>
    <option value="61">+61 Christmas Island</option>
    <option value="61">+61 Cocos Islands</option>
    <option value="57">+57 Colombia</option>
    <option value="269">+269 Comoros</option>
    <option value="682">+682 Cook Islands</option>
    <option value="506">+506 Costa Rica</option>
    <option value="385">+385 Croatia</option>
    <option value="53">+53 Cuba</option>
    <option value="599">+599 Curacao</option>
    <option value="357">+357 Cyprus</option>
    <option value="420">+420 Czech Republic</option>
    <option value="243">+243 DR Congo</option>
    <option value="45">+45 Denmark</option>
    <option value="253">+253 Djibouti</option>
    <option value="1">+1 Dominica</option>
    <option value="1">+1 Dominican Republic</option>
    <option value="593">+593 Ecuador</option>
    <option value="20">+20 Egypt</option>
    <option value="503">+503 El Salvador</option>
    <option value="240">+240 Equatorial Guinea</option>
    <option value="291">+291 Eritrea</option>
    <option value="372">+372 Estonia</option>
    <option value="268">+268 Eswatini</option>
    <option value="251">+251 Ethiopia</option>
    <option value="500">+500 Falkland Islands</option>
    <option value="298">+298 Faroe Islands</option>
    <option value="679">+679 Fiji</option>
    <option value="358">+358 Finland</option>
    <option value="33">+33 France</option>
    <option value="594">+594 French Guiana</option>
    <option value="689">+689 French Polynesia</option>
    <option value="241">+241 Gabon</option>
    <option value="220">+220 Gambia</option>
    <option value="995">+995 Georgia</option>
    <option value="49">+49 Germany</option>
    <option value="233">+233 Ghana</option>
    <option value="350">+350 Gibraltar</option>
    <option value="30">+30 Greece</option>
    <option value="299">+299 Greenland</option>
    <option value="1">+1 Grenada</option>
    <option value="590">+590 Guadeloupe</option>
    <option value="1">+1 Guam</option>
    <option value="502">+502 Guatemala</option>
    <option value="44">+44 Guernsey</option>
    <option value="224">+224 Guinea</option>
    <option value="245">+245 Guinea-Bissau</option>
    <option value="592">+592 Guyana</option>
    <option value="509">+509 Haiti</option>
    <option value="504">+504 Honduras</option>
    <option value="852">+852 Hong Kong</option>
    <option value="36">+36 Hungary</option>
    <option value="354">+354 Iceland</option>
    <option value="91">+91 India</option>
    <option value="62">+62 Indonesia</option>
    <option value="98">+98 Iran</option>
    <option value="964">+964 Iraq</option>
    <option value="353">+353 Ireland</option>
    <option value="44">+44 Isle of Man</option>
    <option value="972">+972 Israel</option>
    <option value="39">+39 Italy</option>
    <option value="1">+1 Jamaica</option>
    <option value="81">+81 Japan</option>
    <option value="44">+44 Jersey</option>
    <option value="962">+962 Jordan</option>
    <option value="7">+7 Kazakhstan</option>
    <option value="254">+254 Kenya</option>
    <option value="686">+686 Kiribati</option>
    <option value="965">+965 Kuwait</option>
    <option value="996">+996 Kyrgyzstan</option>
    <option value="856">+856 Laos</option>
    <option value="371">+371 Latvia</option>
    <option value="961">+961 Lebanon</option>
    <option value="266">+266 Lesotho</option>
    <option value="231">+231 Liberia</option>
    <option value="218">+218 Libya</option>
    <option value="423">+423 Liechtenstein</option>
    <option value="370">+370 Lithuania</option>
    <option value="352">+352 Luxembourg</option>
    <option value="853">+853 Macau</option>
    <option value="389">+389 North Macedonia</option>
    <option value="261">+261 Madagascar</option>
    <option value="265">+265 Malawi</option>
    <option value="60">+60 Malaysia</option>
    <option value="960">+960 Maldives</option>
    <option value="223">+223 Mali</option>
    <option value="356">+356 Malta</option>
    <option value="692">+692 Marshall Islands</option>
    <option value="596">+596 Martinique</option>
    <option value="222">+222 Mauritania</option>
    <option value="230">+230 Mauritius</option>
    <option value="52">+52 Mexico</option>
    <option value="691">+691 Micronesia</option>
    <option value="373">+373 Moldova</option>
    <option value="377">+377 Monaco</option>
    <option value="976">+976 Mongolia</option>
    <option value="382">+382 Montenegro</option>
    <option value="1">+1 Montserrat</option>
    <option value="212">+212 Morocco</option>
    <option value="258">+258 Mozambique</option>
    <option value="95">+95 Myanmar</option>
    <option value="264">+264 Namibia</option>
    <option value="674">+674 Nauru</option>
    <option value="977">+977 Nepal</option>
    <option value="31">+31 Netherlands</option>
    <option value="599">+599 Netherlands Antilles</option>
    <option value="687">+687 New Caledonia</option>
    <option value="64">+64 New Zealand</option>
    <option value="505">+505 Nicaragua</option>
    <option value="227">+227 Niger</option>
    <option value="234">+234 Nigeria</option>
    <option value="683">+683 Niue</option>
    <option value="850">+850 North Korea</option>
    <option value="1">+1 Northern Mariana Islands</option>
    <option value="47">+47 Norway</option>
    <option value="968">+968 Oman</option>
    <option value="92">+92 Pakistan</option>
    <option value="680">+680 Palau</option>
    <option value="970">+970 Palestine</option>
    <option value="507">+507 Panama</option>
    <option value="675">+675 Papua New Guinea</option>
    <option value="595">+595 Paraguay</option>
    <option value="51">+51 Peru</option>
    <option value="63">+63 Philippines</option>
    <option value="48">+48 Poland</option>
    <option value="351">+351 Portugal</option>
    <option value="1">+1 Puerto Rico</option>
    <option value="974">+974 Qatar</option>
    <option value="242">+242 Republic of the Congo</option>
    <option value="262">+262 Reunion</option>
    <option value="40">+40 Romania</option>
    <option value="7">+7 Russia</option>
    <option value="250">+250 Rwanda</option>
    <option value="590">+590 Saint Barthelemy</option>
    <option value="290">+290 Saint Helena</option>
    <option value="1">+1 Saint Kitts and Nevis</option>
    <option value="1">+1 Saint Lucia</option>
    <option value="590">+590 Saint Martin</option>
    <option value="508">+508 Saint Pierre and Miquelon</option>
    <option value="1">+1 Saint Vincent and the Grenadines</option>
    <option value="685">+685 Samoa</option>
    <option value="378">+378 San Marino</option>
    <option value="239">+239 Sao Tome and Principe</option>
    <option value="966">+966 Saudi Arabia</option>
    <option value="221">+221 Senegal</option>
    <option value="381">+381 Serbia</option>
    <option value="248">+248 Seychelles</option>
    <option value="232">+232 Sierra Leone</option>
    <option value="65">+65 Singapore</option>
    <option value="421">+421 Slovakia</option>
    <option value="386">+386 Slovenia</option>
    <option value="677">+677 Solomon Islands</option>
    <option value="252">+252 Somalia</option>
    <option value="27">+27 South Africa</option>
    <option value="82">+82 South Korea</option>
    <option value="211">+211 South Sudan</option>
    <option value="34">+34 Spain</option>
    <option value="94">+94 Sri Lanka</option>
    <option value="249">+249 Sudan</option>
    <option value="597">+597 Suriname</option>
    <option value="46">+46 Sweden</option>
    <option value="41">+41 Switzerland</option>
    <option value="963">+963 Syria</option>
    <option value="886">+886 Taiwan</option>
    <option value="992">+992 Tajikistan</option>
    <option value="255">+255 Tanzania</option>
    <option value="66">+66 Thailand</option>
    <option value="670">+670 Timor-Leste</option>
    <option value="228">+228 Togo</option>
    <option value="690">+690 Tokelau</option>
    <option value="676">+676 Tonga</option>
    <option value="1">+1 Trinidad and Tobago</option>
    <option value="216">+216 Tunisia</option>
    <option value="90">+90 Turkey</option>
    <option value="993">+993 Turkmenistan</option>
    <option value="1">+1 Turks and Caicos Islands</option>
    <option value="688">+688 Tuvalu</option>
    <option value="256">+256 Uganda</option>
    <option value="380">+380 Ukraine</option>
    <option value="971" selected>+971 UAE</option>
    <option value="44">+44 UK</option>
    <option value="1">+1 USA</option>
    <option value="598">+598 Uruguay</option>
    <option value="998">+998 Uzbekistan</option>
    <option value="678">+678 Vanuatu</option>
    <option value="379">+379 Vatican</option>
    <option value="58">+58 Venezuela</option>
    <option value="84">+84 Vietnam</option>
    <option value="681">+681 Wallis and Futuna</option>
    <option value="212">+212 Western Sahara</option>
    <option value="967">+967 Yemen</option>
    <option value="260">+260 Zambia</option>
    <option value="263">+263 Zimbabwe</option>
</select>


        <!-- Phone Number Input -->
        <input type="text" 
               name="phone_number" 
               value="{{ old('phone_number') }}" 
               class="reg-input" 
               required>
    </div>

    @error('phone_number')
        <p class="reg-error">{{ $message }}</p>
    @enderror
</div>


        <!-- ID Number -->
        <div class="reg-group">
            <label class="reg-label">
                <i class="fa fa-id-card reg-icon"></i> ID Number
            </label>
            <input type="text" name="id_number" value="{{ old('id_number') }}" class="reg-input">
            @error('id_number')
                <p class="reg-error">{{ $message }}</p>
            @enderror
        </div>

        <!-- NEW FIELD: Type -->
        <div class="reg-group">
            <label class="reg-label">
                <i class="fa fa-money-bill reg-icon"></i> Type
            </label>
            <select name="type" class="reg-select" required>
                <option value="">Select</option>
                <option value="Cash" {{ old('type')=='Cash' ? 'selected':'' }}>Cash</option>
                <option value="Insurance" {{ old('type')=='Insurance' ? 'selected':'' }}>Insurance</option>
            </select>
            @error('type')
                <p class="reg-error">{{ $message }}</p>
            @enderror
        </div>

        <button class="reg-btn">Continue</button>
    </form>

</div>
@endsection

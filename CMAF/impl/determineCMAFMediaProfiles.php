<?php

global $logger, $mpdHandler;

// Add null checks to prevent crashes when elements don't exist
$ftypElement = $xml->getElementsByTagName("ftyp")->item(0);
$hdlrElement = $xml->getElementsByTagName("hdlr")->item(0);

// Debug information
if (!$ftypElement) {
    fwrite(STDERR, "Warning: No 'ftyp' element found in XML. Available elements: ");
    $allElements = $xml->getElementsByTagName("*");
    $elementNames = array();
    for ($i = 0; $i < min(10, $allElements->length); $i++) {
        $elementNames[] = $allElements->item($i)->nodeName;
    }
    fwrite(STDERR, implode(", ", array_unique($elementNames)) . "\n");
}

if (!$hdlrElement) {
    fwrite(STDERR, "Warning: No 'hdlr' element found in XML\n");
}

$compatibleBrands = $ftypElement ? $ftypElement->getAttribute("compatibleBrands") : "";
$hdlrType = $hdlrElement ? $hdlrElement->getAttribute("handler_type") : "";

if ($hdlrType == 'vide') {
    $mediaProfileParameters = $this->CMAFMediaProfileAttributesVideo;
    $videoSampleDescription = $xml->getElementsByTagName("vide_sampledescription")->item(0);
    
    // Add null check for video sample description
    if (!$videoSampleDescription) {
        fwrite(STDERR, "Warning: No video sample description found in XML\n");
        return array("", array());
    }
    
    $sdType = $videoSampleDescription->getAttribute("sdType");

    if ($sdType == 'avc1' || $sdType == 'avc3') {
        $mediaProfileParameters['codec'] = 'AVC';
        $nalUnits = $xml->getElementsByTagName("NALUnit");
        if ($nalUnits->length != 0) {
            $spsUnitIndex = -1;
            for ($nalIndex = 0; $nalIndex < $nalUnits->length; $nalIndex++) {
                if (hexdec($nalUnits->item($nalIndex)->getAttribute("nal_type")) == 7) {
                    $spsUnitIndex = $nalIndex;
                    break;
                }
            }


            ///\Resiliency Add checks for existing spsUnit
            $avcC = $videoSampleDescription->getElementsByTagName('avcC')->item(0);
            $comment = $nalUnits->item($spsUnitIndex)->getElementsByTagName("comment")->item(0);
            
            // Add null checks to prevent crashes
            if (!$avcC) {
                fwrite(STDERR, "Warning: No avcC element found\n");
                return array("", array());
            }
            if (!$comment) {
                fwrite(STDERR, "Warning: No comment element found in NAL unit\n");
                return array("", array());
            }
            
            $mediaProfileParameters['profile'] = hexdec($avcC->getAttribute("profile"));
            $mediaProfileParameters['level'] = (float)($comment->getAttribute("level_idc")) / 10;
            $mediaProfileParameters['width'] = $videoSampleDescription->getAttribute("width");
            $mediaProfileParameters['height'] = $videoSampleDescription->getAttribute("height");

            if (hexdec($comment->getAttribute("vui_parameters_present_flag")) == 1) {
                if (hexdec($comment->getAttribute("video_signal_type_present_flag")) == 1) {
                    if (hexdec($comment->getAttribute("colour_description_present_flag")) == 1) {
                        $mediaProfileParameters['color_primaries'] = hexdec(
                            $comment->getAttribute("colour_primaries")
                        );
                        $mediaProfileParameters['transfer_char'] = hexdec(
                            $comment->getAttribute("transfer_characteristics")
                        );
                        $mediaProfileParameters['matrix_coeff'] = hexdec(
                            $comment->getAttribute("matrix_coefficients")
                        );
                    } elseif (hexdec($comment->getAttribute("colour_description_present_flag")) == 0) {
                        $mediaProfileParameters['color_primaries'] = 1;
                        $mediaProfileParameters['transfer_char'] = 1;
                        $mediaProfileParameters['matrix_coeff'] = 1;
                    }
                }
                if (hexdec($comment->getAttribute("timing_info_present_flag")) == 1) {
                    $mediaProfileParameters['framerate'] = $mpdHandler->getFrameRate();
                }
            }

            if (strpos($compatibleBrands, "cfsd") !== false) {
                $mediaProfileParameters['brand'] = "cfsd";
            }
            if (strpos($compatibleBrands, "cfhd") !== false) {
                $mediaProfileParameters['brand'] = "cfhd";
            }
        }
    }
    if ($sdType == 'hev1' || $sdType == 'hvc1') {
        $mediaProfileParameters['codec'] = "HEVC";
        $hvcCBoxes = $xml->getElementsByTagName("hvcC");
        if ($hvcCBoxes->length > 0) {
            $hvcCBox = $hvcCBoxes->item(0);
            if (
                $hvcCBox->getAttribute("profile_idc") == "1" ||
                $hvcCBox->getAttribute("compatibility_flag_1") == "1"
            ) {
                $profile = "Main";
            } elseif (
                $hvcCBox->getAttribute("profile_idc") == "2" ||
                $hvcCBox->getAttribute("compatibility_flag_2") == "1"
            ) {
                $profile = "Main10";
            } else {
                $profile = "Other";
            }

            //Tier=0 is the main-tier.
            $mediaProfileParameters['tier'] = $hvcCBox->getAttribute("tier_flag");
            $mediaProfileParameters['profile'] = $profile;
            //HEVC std defines level_idc is 30 times of actual level number.
            $mediaProfileParameters['level'] = (float)($hvcCBox->getAttribute("level_idc")) / 30;
        }

        $mediaProfileParameters['width'] = $videoSampleDescription->getAttribute("width");
        $mediaProfileParameters['height'] = $videoSampleDescription->getAttribute("height");
        $nalUnits = $xml->getElementsByTagName("NALUnit");
        if ($nalUnits->length != 0) {
            for ($nalIndex = 0; $nalIndex < $nalUnits->length; $nalIndex++) {
                if ($nalUnits->item($nalIndex)->getAttribute("nal_unit_type") == "33") {
                    $spsUnitIndex = $nalIndex;
                    break;
                }
            }

            ///\Resiliency Add checks for existing spsUnit
            $sps = $nalUnits->item($spsUnitIndex);
            if ($sps->getAttribute("vui_parameters_present_flag") == "1") {
                if ($sps->getAttribute("video_signal_type_present_flag") == "1") {
                    if ($sps->getAttribute("colour_description_present_flag") == "1") {
                        $mediaProfileParameters['color_primaries'] = hexdec(
                            $sps->getAttribute("colour_primaries")
                        );
                        $mediaProfileParameters['transfer_char'] = hexdec(
                            $sps->getAttribute("transfer_characteristics")
                        );
                        $mediaProfileParameters['matrix_coeff'] = hexdec(
                            $sps->getAttribute("matrix_coeffs")
                        );
                    } elseif ($sps->getAttribute("colour_description_present_flag") == "0") {
                        $mediaProfileParameters['color_primaries'] = 1;
                        $mediaProfileParameters['transfer_char'] = 1;
                        $mediaProfileParameters['matrix_coeff'] = 1;
                    }
                }
                if ($sps->getAttribute("vui_timing_info_present_flag") == "1") {
                    $mediaProfileParameters['framerate'] = $mpdHandler->getFrameRate();
                }
            }

            if (strpos($compatibleBrands, "chhd")) {
                $mediaProfileParameters['brand'] = "chhd";
            } elseif (strpos($compatibleBrands, "chh1")) {
                $mediaProfileParameters['brand'] = "chh1";
            } elseif (strpos($compatibleBrands, "cud8")) {
                $mediaProfileParameters['brand'] = "cud8";
            } elseif (strpos($compatibleBrands, "cud1")) {
                $mediaProfileParameters['brand'] = "cud1";
            } elseif (strpos($compatibleBrands, "chd1")) {
                $mediaProfileParameters['brand'] = "chd1";
            } elseif (strpos($compatibleBrands, "clg1")) {
                $mediaProfileParameters['brand'] = "clg1";
            }
        }
    }

    return $this->getVideoTrackMediaProfile($mediaProfileParameters);
}
if ($hdlrType == 'soun') {
    $mediaProfileParameters = $this->CMAFMediaProfileAttributesAudio;
    $audioSampleDescription = $xml->getElementsByTagName("soun_sampledescription")->item(0);
    $sdType = $audioSampleDescription->getAttribute("sdType");
    if ($sdType == "mp4a") {
        $mediaProfileParameters['codec'] = "AAC";
        $decoderSpecificInfo = $audioSampleDescription->getElementsByTagName("DecoderSpecificInfo")->item(0);
        $mediaProfileParameters['sampleRate'] = $audioSampleDescription->getAttribute('sampleRate');
        $mediaProfileParameters['profile'] = hexdec($decoderSpecificInfo->getAttribute("audioObjectType"));
        $mediaProfileParameters['channels'] = hexdec($decoderSpecificInfo->getAttribute("channelConfig"));

        if (strpos($compatibleBrands, "caaa") !== false) {
            $mediaProfileParameters['brand'] = "caaa";
        } elseif (strpos($compatibleBrands, "caac")) {
            $mediaProfileParameters['brand'] = "caac";
        }

        $iodsODBoxes = $xml->getElementsByTagName("iods_OD");
        if ($iodsODBoxes->length > 0) {
            $iodsComment = $iodsODBoxes->getAttribute("Comment");
            if ($iodsComment !== null) {
                $mediaProfileParameters['level'] == str_replace("audio profile/level is ", "", $iodsComment);
            }
        }
    }
    return $this->getAudioTrackMediaProfile($mediaProfileParameters);
}
if ($hdlrType == 'text') {
    $mediaProfileParameters = $this->CMAFMediaProfileAttributesSubtitle;
    $testSampleDescription = $xml->getElementsByTagName("text_sampledescription")->item(0);
    $sdType = $testSampleDescription->getAttribute("sdType");
    if ($sdType == 'wvtt') {
        $mediaProfileParameters['codec'] = "WebVTT";

        if (strpos($compatibleBrands, "cwvt") !== false) {
            $mediaProfileParameters['brand'] = "cwvt";
        }
    }
    return $this->getSubtitleTrackMediaProfile($mediaProfileParameters);
}
if ($hdlrType == 'subt') {
    $mediaProfileParameters = $this->CMAFMediaProfileAttributesSubtitle;
    $subtitleSampleDescription = $xml->getElementsByTagName("subt_sampledescription")->item(0);
    $sdType = $subtitleSampleDescription->getAttribute("sdType");
    if ($sdType == "stpp") {
        $mime = $subtitleSampleDescription->getElementsByTagName("mime");
        if ($mime->length > 0) {
            $contentType = $mime->getAttribute("content_type");
            $subtypePosition = strpos($contentType, "ttml+xml") || strpos($contentType, "mp4");
            $codecPosition = strpos($contentType, "im1t") || strpos($contentType, "im1i");
            $mediaProfileParameters['mimeType'] = "";
            if (strpos($contentType, "application") !== false) {
                $mediaProfileParameters['mimeType'] = "application";
            }

            $mediaProfileParameters['codec'] = "";
            if ($codecPosition !== false) {
                $mediaProfileParameters['codec'] = substr($contentType, $codecPosition, $codecPosition + 3);
            }

            if (strpos($contentType, "ttml+xml") !== false) {
                $mediaProfileParameters['mimeSubtype'] = "ttml+xml";
            } elseif (strpos($contentType, "mp4") !== false) {
                $mediaProfileParameters['mimeSubtype'] = "mp4";
            }
        }
        if (strpos($compatibleBrands, "im1t") !== false) {
            $mediaProfileParameters['brand'] = "im1t";
        }
        if (strpos($compatibleBrands, "im1i") !== false) {
            $mediaProfileParameters['brand'] = "im1i";
        }
    }
    return $this-> getSubtitleTrackMediaProfile($mediaProfileParameters);
}
return null;

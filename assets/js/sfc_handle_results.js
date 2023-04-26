jQuery(document).ready(function ($) {
    $('#sfc-resume-comparison-tool-form').submit(function (event) {
        event.preventDefault();

        const sfcResumeFile = document.getElementById('resume_file').files[0];
        const sfcJobDescriptionFile = document.getElementById('job_description_file').files[0];

        if (!sfcResumeFile || !sfcJobDescriptionFile) {
            alert('Please select both a resume file and a job description file.');
            return;
        }

        const allowedTypes = ['.doc', '.docx', '.pdf', '.txt'];
        const resumeFileExtension = sfcResumeFile.name.split('.').pop().toLowerCase();
        const jobDescriptionFileExtension = sfcJobDescriptionFile.name.split('.').pop().toLowerCase();

        if (!allowedTypes.includes('.' + resumeFileExtension)) {
            alert('Invalid file type. Please select a file in .doc, .docx, .pdf, or .txt format for the resume file.');
            return;
        }

        if (!allowedTypes.includes('.' + jobDescriptionFileExtension)) {
            alert('Invalid file type. Please select a file in .doc, .docx, .pdf, or .txt format for the job description file.');
            return;
        }

        const maxFileSize = sfcResumeComparisonToolAjax.maxFileSize;
        const resumeFileSize = sfcResumeFile.size;
        const jobDescriptionFileSize = sfcJobDescriptionFile.size;

        if (resumeFileSize > maxFileSize || jobDescriptionFileSize > maxFileSize) {
            alert('File size exceeds the limit of ' + (maxFileSize / (1024 * 1024)) + 'MB. Please select a smaller file.');
            return;
        }

        const sfcFormData = new FormData();
        sfcFormData.append('_ajax_nonce', sfcResumeComparisonToolAjax.nonce); 
        sfcFormData.append('action', 'sfc_handle_comparison_ajax');
        sfcFormData.append('resume_file', sfcResumeFile);
        sfcFormData.append('job_description_file', sfcJobDescriptionFile);

        $.ajax({
            url: sfcResumeComparisonToolAjax.ajaxUrl,
            type: 'POST',
            data: sfcFormData,
            contentType: false,
            processData: false,
            cache: false,
            enctype: 'multipart/form-data',
            success: function (response) {
                if (response.success) {
                    const wordMatchPercentage = response.data;
                    const color = wordMatchPercentage > 50 ? 'green' : 'red';
                    const resultsHtml = '<div class="resume-comparison-results"><h4>Your Comparison Results</h4><br><p>The word match percentage between your resume and the job description is: <br><span style="font-size: 3.0em; color: ' + color + ';">' + wordMatchPercentage + '%</span></p></div>';
                    $('#sfc-resume-comparison-tool-results').html(resultsHtml);
                } else {
                    console.log(response.data);
                    $('#sfc-resume-comparison-tool-results').empty();
                    alert(response.data);
                }
            },
            error: function (error) {
                console.log(error);
                $('#sfc-resume-comparison-tool-results').empty();
                alert(error.responseText);
            }
        });
    });
});


jQuery(document).ready(function ($) {
    $('#reset').click(function () {
        $('#resume_file').val('');
        $('#job_description_file').val('');
        $('#sfc-resume-comparison-tool-results').empty();
    });
});











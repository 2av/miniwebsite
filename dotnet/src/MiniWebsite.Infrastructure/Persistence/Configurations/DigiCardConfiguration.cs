using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using MiniWebsite.Domain.Entities;

namespace MiniWebsite.Infrastructure.Persistence.Configurations;

public class DigiCardConfiguration : IEntityTypeConfiguration<DigiCard>
{
    public void Configure(EntityTypeBuilder<DigiCard> builder)
    {
        builder.ToTable("digi_card");
        builder.HasKey(x => x.Id);
        builder.Property(x => x.Id).HasColumnName("id");
        builder.Property(x => x.Ip).HasColumnName("ip").HasMaxLength(50);
        builder.Property(x => x.FUserEmail).HasColumnName("f_user_email").HasMaxLength(200);
        builder.Property(x => x.UserEmail).HasColumnName("user_email").HasMaxLength(200);
        builder.Property(x => x.CardId).HasColumnName("card_id").HasMaxLength(200);
        builder.Property(x => x.Password).HasColumnName("password").HasMaxLength(200);
        builder.Property(x => x.DCss).HasColumnName("d_css").HasMaxLength(100);
        builder.Property(x => x.DMobileCss).HasColumnName("d_mobile_css").HasMaxLength(100);
        builder.Property(x => x.DCompName).HasColumnName("d_comp_name").HasMaxLength(200);
        builder.Property(x => x.DDisplayName).HasColumnName("d_display_name").HasMaxLength(199);
        builder.Property(x => x.DTitle).HasColumnName("d_title").HasMaxLength(10);
        builder.Property(x => x.DFName).HasColumnName("d_f_name").HasMaxLength(30);
        builder.Property(x => x.DLName).HasColumnName("d_l_name").HasMaxLength(30);
        builder.Property(x => x.DPosition).HasColumnName("d_position").HasMaxLength(30);
        builder.Property(x => x.DContact).HasColumnName("d_contact").HasMaxLength(30);
        builder.Property(x => x.DContact2).HasColumnName("d_contact2").HasMaxLength(30);
        builder.Property(x => x.DWhatsapp).HasColumnName("d_whatsapp").HasMaxLength(30);
        builder.Property(x => x.DAddress).HasColumnName("d_address").HasMaxLength(500);
        builder.Property(x => x.DEmail).HasColumnName("d_email").HasMaxLength(100);
        builder.Property(x => x.DWebsite).HasColumnName("d_website").HasMaxLength(150);
        builder.Property(x => x.DLocation).HasColumnName("d_location").HasMaxLength(1000);
        builder.Property(x => x.DFb).HasColumnName("d_fb").HasMaxLength(700);
        builder.Property(x => x.DTwitter).HasColumnName("d_twitter").HasMaxLength(700);
        builder.Property(x => x.DInstagram).HasColumnName("d_instagram").HasMaxLength(700);
        builder.Property(x => x.DLinkedin).HasColumnName("d_linkedin").HasMaxLength(500);
        builder.Property(x => x.DYoutube).HasColumnName("d_youtube").HasMaxLength(300);
        builder.Property(x => x.DPinterest).HasColumnName("d_pinterest").HasMaxLength(500);
        builder.Property(x => x.DWebsite2).HasColumnName("d_website2").HasMaxLength(500);
        builder.Property(x => x.DCompEstDate).HasColumnName("d_comp_est_date").HasMaxLength(100);
        builder.Property(x => x.DNatureOfBusiness).HasColumnName("d_nature_of_business").HasMaxLength(200);
        builder.Property(x => x.DSpeciality).HasColumnName("d_speciality").HasMaxLength(200);
        builder.Property(x => x.DAboutUs).HasColumnName("d_about_us").HasMaxLength(2000);
        builder.Property(x => x.DPaytm).HasColumnName("d_paytm").HasMaxLength(100);
        builder.Property(x => x.DGooglePay).HasColumnName("d_google_pay").HasMaxLength(100);
        builder.Property(x => x.DPhonePay).HasColumnName("d_phone_pay").HasMaxLength(100);
        builder.Property(x => x.DAccountNo).HasColumnName("d_account_no").HasMaxLength(40);
        builder.Property(x => x.DIfsc).HasColumnName("d_ifsc").HasMaxLength(40);
        builder.Property(x => x.DAcName).HasColumnName("d_ac_name").HasMaxLength(100);
        builder.Property(x => x.DBankName).HasColumnName("d_bank_name").HasMaxLength(100);
        builder.Property(x => x.DAcType).HasColumnName("d_ac_type").HasMaxLength(30);
        builder.Property(x => x.DPaymentStatus).HasColumnName("d_payment_status").HasMaxLength(200);
        builder.Property(x => x.DCardStatus).HasColumnName("d_card_status").HasMaxLength(200);
        builder.Property(x => x.DPaymentAmount).HasColumnName("d_payment_amount").HasMaxLength(200);
        builder.Property(x => x.DOrderId).HasColumnName("d_order_id").HasMaxLength(200);
        builder.Property(x => x.DLogoLocation).HasColumnName("d_logo_location").HasMaxLength(1000);
        builder.Property(x => x.UploadedDate).HasColumnName("uploaded_date");
        builder.Property(x => x.DPaymentDate).HasColumnName("d_payment_date");
        builder.Property(x => x.DGst).HasColumnName("d_gst").HasMaxLength(50);
        builder.Property(x => x.DGstName).HasColumnName("d_gst_name").HasMaxLength(100);
        builder.Property(x => x.DGstAddress).HasColumnName("d_gst_address").HasMaxLength(255);
        builder.Property(x => x.DGstState).HasColumnName("d_gst_state").HasMaxLength(50);
        builder.Property(x => x.DGstCity).HasColumnName("d_gst_city").HasMaxLength(50);
        builder.Property(x => x.DGstPincode).HasColumnName("d_gst_pincode").HasMaxLength(20);
        builder.Property(x => x.CollaborationEnabled).HasColumnName("collaboration_enabled").HasMaxLength(3);
        builder.Property(x => x.ComplimentaryEnabled).HasColumnName("complimentary_enabled").HasMaxLength(10);
        builder.Property(x => x.DGstEmail).HasColumnName("d_gst_email").HasMaxLength(100);
        builder.Property(x => x.DGstContact).HasColumnName("d_gst_contact").HasMaxLength(20);
        builder.Property(x => x.ValidityDate).HasColumnName("validity_date");
        builder.Property(x => x.NameChangeCount).HasColumnName("name_change_count");
        builder.Property(x => x.DNameChangeCount).HasColumnName("d_name_change_count");
        builder.Property(x => x.DHeroImageLocation).HasColumnName("d_hero_image_location").HasMaxLength(255);
        builder.Property(x => x.DPositionPrimary).HasColumnName("d_position_primary").HasMaxLength(200).IsRequired();
        builder.Property(x => x.DPositionSecondary).HasColumnName("d_position_secondary").HasMaxLength(200).IsRequired();
        builder.Property(x => x.DGstNumber).HasColumnName("d_gst_number").HasMaxLength(100).IsRequired();
        builder.Property(x => x.DAddress2).HasColumnName("d_address2").HasMaxLength(500).IsRequired();
        builder.Property(x => x.DCity).HasColumnName("d_city").HasMaxLength(200).IsRequired();
        builder.Property(x => x.DState).HasColumnName("d_state").HasMaxLength(200).IsRequired();
        builder.Property(x => x.DPincode).HasColumnName("d_pincode").HasMaxLength(50).IsRequired();
        builder.Property(x => x.DCountry).HasColumnName("d_country").HasMaxLength(200).IsRequired();
        builder.Property(x => x.DBusinessHours).HasColumnName("d_business_hours");

        builder.Property(x => x.DYoutube1).HasColumnName("d_youtube1").HasMaxLength(150);
        builder.Property(x => x.DYoutube2).HasColumnName("d_youtube2").HasMaxLength(150);
        builder.Property(x => x.DYoutube3).HasColumnName("d_youtube3").HasMaxLength(150);
        builder.Property(x => x.DYoutube4).HasColumnName("d_youtube4").HasMaxLength(150);
        builder.Property(x => x.DYoutube5).HasColumnName("d_youtube5").HasMaxLength(150);
        builder.Property(x => x.DYoutube6).HasColumnName("d_youtube6").HasMaxLength(150);
        builder.Property(x => x.DYoutube7).HasColumnName("d_youtube7").HasMaxLength(150);
        builder.Property(x => x.DYoutube8).HasColumnName("d_youtube8").HasMaxLength(150);
        builder.Property(x => x.DYoutube9).HasColumnName("d_youtube9").HasMaxLength(150);
        builder.Property(x => x.DYoutube10).HasColumnName("d_youtube10").HasMaxLength(150);
        builder.Property(x => x.DYoutube11).HasColumnName("d_youtube11").HasMaxLength(150);
        builder.Property(x => x.DYoutube12).HasColumnName("d_youtube12").HasMaxLength(150);
        builder.Property(x => x.DYoutube13).HasColumnName("d_youtube13").HasMaxLength(150);
        builder.Property(x => x.DYoutube14).HasColumnName("d_youtube14").HasMaxLength(150);
        builder.Property(x => x.DYoutube15).HasColumnName("d_youtube15").HasMaxLength(150);
        builder.Property(x => x.DYoutube16).HasColumnName("d_youtube16").HasMaxLength(150);
        builder.Property(x => x.DYoutube17).HasColumnName("d_youtube17").HasMaxLength(150);
        builder.Property(x => x.DYoutube18).HasColumnName("d_youtube18").HasMaxLength(150);
        builder.Property(x => x.DYoutube19).HasColumnName("d_youtube19").HasMaxLength(150);
        builder.Property(x => x.DYoutube20).HasColumnName("d_youtube20").HasMaxLength(150);

        builder.Property(x => x.DProName1).HasColumnName("d_pro_name1").HasMaxLength(100);
        builder.Property(x => x.DProName2).HasColumnName("d_pro_name2").HasMaxLength(100);
        builder.Property(x => x.DProName3).HasColumnName("d_pro_name3").HasMaxLength(100);
        builder.Property(x => x.DProName4).HasColumnName("d_pro_name4").HasMaxLength(100);
        builder.Property(x => x.DProName5).HasColumnName("d_pro_name5").HasMaxLength(100);
        builder.Property(x => x.DProName6).HasColumnName("d_pro_name6").HasMaxLength(100);
        builder.Property(x => x.DProName7).HasColumnName("d_pro_name7").HasMaxLength(100);
        builder.Property(x => x.DProName8).HasColumnName("d_pro_name8").HasMaxLength(100);
        builder.Property(x => x.DProName9).HasColumnName("d_pro_name9").HasMaxLength(100);
        builder.Property(x => x.DProName10).HasColumnName("d_pro_name10").HasMaxLength(100);

        // longblobs not mapped: d_logo, d_qr_paytm, d_qr_google_pay, d_qr_phone_pay
        // video type/thumb columns not needed for dashboard list — skip to keep model lean
    }
}
